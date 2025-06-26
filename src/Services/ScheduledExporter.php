<?php

namespace VisualBuilder\ExportScheduler\Services;

use AnourValar\EloquentSerialize\Facades\EloquentSerializeFacade;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Jobs\CreateXlsxFile;
use Filament\Actions\Exports\Models\Export;
use Carbon\Carbon;
use Illuminate\Bus\PendingBatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\PendingChain;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use VisualBuilder\ExportScheduler\Enums\DateRange;
use VisualBuilder\ExportScheduler\Jobs\PrepareCsvExport;
use VisualBuilder\ExportScheduler\Jobs\ScheduledExportCompletion;
use VisualBuilder\ExportScheduler\Models\ExportSchedule;

class ScheduledExporter
{
    protected ?Exporter $exporterInstance = null;

    protected ?Export $export = null;

    protected ?Builder $query = null;

    protected array $columnMap = [];

    protected array $options = [];

    protected array $relations = [];

    public function __construct(public ExportSchedule $exportSchedule)
    {
    }

    public function getTotalRows(): int
    {
        return $this->export?->total_rows ?? 0;
    }

    public function run(): bool
    {
        return $this->init() && $this->buildJobChain();
    }

    /**
     * Create the export record and calculate the query results count
     */
    protected function init(): bool
    {
        try {
            $exporter = $this->exportSchedule->exporter;
            $this->exportSchedule->loadMissing('owner');
            // Get the query from the exporter class
            $this->query = $exporter::getModel()::query();
            $this->query = $exporter::modifyQuery($this->query);

            // Apply custom date range filter if available
            if ($this->exportSchedule->date_range) {
                // Default to 'created_at' if method doesn't exist
                $dateColumn = method_exists($exporter, 'getDateColumn') ? $exporter::getDateColumn() : 'created_at';

                ['start' => $startDate, 'end' => $endDate] = $this->exportSchedule->date_range->getDateRange();
                $this->query->whereBetween($dateColumn, [$startDate, $endDate]);
            }

            // Apply custom relation filter if available
            $filters = $this->exportSchedule->filters ?? [];
            $relationFilters = array_filter($filters, fn($key) => $key !== 'attributes', ARRAY_FILTER_USE_KEY);
            $attributeFilters = array_diff_key($filters, $relationFilters)['attributes'] ?? [];

            // filter by relations
            foreach ($relationFilters as $relation => $selectedRelations) {
                $this->query->whereHas($relation, fn($query) => $query->whereIn('id', $selectedRelations));
            }

            // filter by attributes
            if (filled($attributeFilters)) {
                $this->query->where(function ($query) use ($attributeFilters) {
                    foreach ($attributeFilters as $filter) {
                        $column = $filter['column'] ?? null;
                        $value = $filter['value'] ?? null;
                        $operator = $filter['operator'] ?? null;
                        $condition = $filter['condition'] ?? 'and';

                        if (blank($column) || blank($value)) {
                            continue;
                        }

                        // support nested relations with dot notation
                        if (str_contains($column, '.')) {
                            $parts = explode('.', $column);
                            $column = array_pop($parts);
                            $relationPath = implode('.', $parts);

                            $firstRelation = $parts[0];
                            $modelClass = $this->exportSchedule->exporter::getModel();
                            $relationMethod = method_exists($modelClass, $firstRelation)
                                ? (new $modelClass)->$firstRelation()
                                : null;

                            if ($relationMethod instanceof \Illuminate\Database\Eloquent\Relations\MorphTo) {
                                $remainingPath = implode('.', array_slice($parts, 1));
                                $types = $this->getMorphTypes();
                                $query->{$condition === 'or' ? 'orWhereHasMorph' : 'whereHasMorph'}($firstRelation, $types, function ($morphQuery) use ($modelClass, $remainingPath, $column, $operator, $value) {
                                    if ($remainingPath && method_exists($modelClass, $remainingPath)) {
                                        $morphQuery->whereHas($remainingPath, function ($subQuery) use ($column, $operator, $value) {
                                            if ($operator === 'since' && is_array($value)) {
                                                $date = Carbon::now();
                                                $amount = (int) ($value['amount'] ?? 0);
                                                $unit = $value['unit'] ?? 'days';
                                                match ($unit) {
                                                    'weeks' => $date->subWeeks($amount),
                                                    'months' => $date->subMonths($amount),
                                                    'years' => $date->subYears($amount),
                                                    default => $date->subDays($amount),
                                                };
                                                $subQuery->where($column, '>=', $date);
                                            } elseif (in_array($operator, ['in', 'not_in']) && is_array($value)) {
                                                $subQuery->{$operator === 'in' ? 'whereIn' : 'whereNotIn'}($column, $value);
                                            } elseif ($operator === 'like') {
                                                $subQuery->where($column, 'LIKE', "%$value%");
                                            } else {
                                                $subQuery->where($column, $operator, $value);
                                            }
                                        });
                                    } else {
                                    if ($operator === 'since' && is_array($value)) {
                                            $date = Carbon::now();
                                            $amount = (int) ($value['amount'] ?? 0);
                                            $unit = $value['unit'] ?? 'days';
                                            match ($unit) {
                                                'weeks' => $date->subWeeks($amount),
                                                'months' => $date->subMonths($amount),
                                                'years' => $date->subYears($amount),
                                                default => $date->subDays($amount),
                                            };
                                            $morphQuery->where($column, '>=', $date);
                                        } elseif (in_array($operator, ['in', 'not_in']) && is_array($value)) {
                                            $morphQuery->{$operator === 'in' ? 'whereIn' : 'whereNotIn'}($column, $value);
                                        } elseif ($operator === 'like') {
                                            $morphQuery->where($column, 'LIKE', "%$value%");
                                        } else {
                                            $morphQuery->where($column, $operator, $value);
                                        }
                                    }
                                });
                            } else {
                                $query->{$condition === 'or' ? 'orWhereHas' : 'whereHas'}($relationPath, function ($subQuery) use ($column, $operator, $value) {
                                    if (in_array($operator, ['in', 'not_in']) && is_array($value)) {
                                        $subQuery->{$operator === 'in' ? 'whereIn' : 'whereNotIn'}($column, $value);
                                    } elseif ($operator === 'like') {
                                        $subQuery->where($column, 'LIKE', "%$value%");
                                    } else {
                                        $subQuery->where($column, $operator, $value);
                                    }
                                });
                            }
                        } else {
                            if ($operator === '<>' && filled($dateRange = DateRange::tryFrom($value))) {
                                ['start' => $startDate, 'end' => $endDate] = $dateRange->getDateRange();
                                $query->{$condition === 'or' ? 'orWhereBetween' : 'whereBetween'}($column, [$startDate, $endDate]);
                            } elseif ($operator === 'since' && is_array($value)) {
                                $date = Carbon::now();
                                $amount = (int) ($value['amount'] ?? 0);
                                $unit = $value['unit'] ?? 'days';
                                match ($unit) {
                                    'weeks' => $date->subWeeks($amount),
                                    'months' => $date->subMonths($amount),
                                    'years' => $date->subYears($amount),
                                    default => $date->subDays($amount),
                                };
                                $query->{$condition === 'or' ? 'orWhere' : 'where'}($column, '>=', $date);
                            } elseif (in_array($operator, ['in', 'not_in']) && is_array($value)) {
                                $query->{$condition === 'or' ? 'orWhereIn' : 'whereIn'}($column, $value);
                            } elseif ($operator === 'like') {
                                $query->{$condition === 'or' ? 'orWhere' : 'where'}($column, 'LIKE', "%$value%");
                            } else {
                                $query->{$condition === 'or' ? 'orWhere' : 'where'}($column, $operator, $value);
                            }
                        }
                    }
                });
            }

            // Prepare column mappings
            $this->columnMap = [];
            foreach ($this->exportSchedule->columns as $column) {
                $this->columnMap[$column['name']] = $column['label'] ?? $column['name'];
            }

            // Prepare options if needed
            $this->options = [];
            // Create Export instance
            $export = new Export;
            $export->exporter = $exporter;
            $export->total_rows = $this->query->count();
            $export->file_disk = config('export-scheduler.file_disk');
            $export->file_name = $this->generateFileName();
            $export->user()->associate($this->exportSchedule->owner);
            $export->save();
            $this->export = $export;

            $this->exporterInstance = $export->getExporter(
                columnMap: $this->columnMap,
                options: $this->options
            );

            return true;
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());

            return false;
        }
    }

    protected function generateFileName(): string
    {
        return Str::slug($this->exportSchedule->name . '_' . now()->format('Y-m-d_Hi'));
    }

    protected function getMorphTypes(): array
    {
        $types = collect(config('export-scheduler.user_models', []))
            ->map(function ($model) {
                return is_array($model) ? ($model['model'] ?? null) : $model;
            })
            ->filter()
            ->values();

        if ($this->exportSchedule->owner_type) {
            $types->push($this->exportSchedule->owner_type);
        }

        return $types->unique()->all();
    }

    public function buildJobChain(): bool
    {
        try {
            $formats = $this->exportSchedule->formats;
            $hasXlsx = in_array(ExportFormat::Xlsx, $formats);
            $serializedQuery = EloquentSerializeFacade::serialize($this->query);

            $job = PrepareCsvExport::class;
            $jobQueue = $this->exporterInstance->getJobQueue();
            $jobConnection = $this->exporterInstance->getJobConnection();
            $jobBatchName = $this->exporterInstance->getJobBatchName();

            // We do not want to send the loaded user relationship to the queue in job payloads,
            // in case it contains attributes that are not serializable, such as binary columns.
            $this->export->unsetRelation('user');

            $makeCreateXlsxFileJob = fn(): CreateXlsxFile => app(CreateXlsxFile::class, [
                'export' => $this->export,
                'columnMap' => $this->columnMap,
                'options' => $this->options
            ]);

            Bus::chain([// 1. Batch Job: Processes the export data (CSV).
                Bus::batch([
                    app($job, [
                        'export' => $this->export,
                        'query' => $serializedQuery,
                        'columnMap' => $this->columnMap,
                        'options' => $this->options,
                        'chunkSize' => 100,
                        'records' => null
                    ])
                ])
                    ->when(filled($jobQueue), fn(PendingBatch $batch) => $batch->onQueue($jobQueue))
                    ->when(filled($jobConnection), fn(PendingBatch $batch) => $batch->onConnection($jobConnection))
                    ->when(filled($jobBatchName), fn(PendingBatch $batch) => $batch->name($jobBatchName))
                    ->allowFailures(),

                // 2. Conditional Job: CreateXlsxFile if XLSX format is requested.
                ...($hasXlsx ? [$makeCreateXlsxFileJob()] : []),

                // 3. ScheduledExportCompletion Job: Marks export as complete after all files are ready.
                new ScheduledExportCompletion(
                    export: $this->export,
                    exportSchedule: $this->exportSchedule
                )
            ])
                ->when(filled($jobQueue), fn(PendingChain $chain) => $chain->onQueue($jobQueue))
                ->when(filled($jobConnection), fn(PendingChain $chain) => $chain->onConnection($jobConnection))
                ->dispatch();

            return true;
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());

            return false;
        }
    }
}
