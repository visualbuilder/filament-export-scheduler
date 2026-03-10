<?php

namespace Visualbuilder\ExportScheduler\Services;

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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Visualbuilder\ExportScheduler\Enums\DateRange;
use Visualbuilder\ExportScheduler\Jobs\ExportSqlQuery;
use Visualbuilder\ExportScheduler\Jobs\PrepareCsvExport;
use Visualbuilder\ExportScheduler\Jobs\ScheduledExportCompletion;
use Visualbuilder\ExportScheduler\Models\ExportSchedule;

class ScheduledExporter
{
    protected ?Exporter $exporterInstance = null;

    protected ?Export $export = null;

    protected ?Builder $query = null;

    protected array $columnMap = [];

    protected array $options = [];

    protected array $relations = [];

    protected $runUser = null;

    public function __construct(public ExportSchedule $exportSchedule)
    {
    }

    public function getTotalRows(): int
    {
        return $this->export?->total_rows ?? 0;
    }

    protected function buildBaseQuery(): Builder
    {
        $exporter = $this->exportSchedule->exporter;

        $query = $exporter::getModel()::query();
        $query = $exporter::modifyQuery($query);

        if ($this->exportSchedule->date_range) {
            $dateColumn = method_exists($exporter, 'getDateColumn') ? $exporter::getDateColumn() : 'created_at';
            ['start' => $startDate, 'end' => $endDate] = $this->exportSchedule->date_range->getDateRange();
            $query->whereBetween($dateColumn, [$startDate, $endDate]);
        }

        $filters = $this->exportSchedule->filters ?? [];
        $relationFilters = array_filter($filters, fn($key) => $key !== 'attributes', ARRAY_FILTER_USE_KEY);
        $attributeFilters = array_diff_key($filters, $relationFilters)['attributes'] ?? [];

        foreach ($relationFilters as $relation => $selectedRelations) {
            $query->whereHas($relation, fn($q) => $q->whereIn('id', $selectedRelations));
        }

        if (filled($attributeFilters)) {
            $query->where(function ($q) use ($attributeFilters) {
                foreach ($attributeFilters as $filter) {
                    $column = $filter['column'] ?? null;
                    $value = $filter['value'] ?? null;
                    $operator = $filter['operator'] ?? null;
                    $condition = $filter['condition'] ?? 'and';

                    if (blank($column) || blank($value)) {
                        continue;
                    }

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
                            $q->{$condition === 'or' ? 'orWhereHasMorph' : 'whereHasMorph'}($firstRelation, $types, function ($morphQuery) use ($modelClass, $remainingPath, $column, $operator, $value) {
                                if ($remainingPath && method_exists($modelClass, $remainingPath)) {
                                    $morphQuery->whereHas($remainingPath, function ($subQuery) use ($column, $operator, $value) {
                                        $this->applyAttributeFilter($subQuery, $column, $operator, $value);
                                    });
                                } else {
                                    $this->applyAttributeFilter($morphQuery, $column, $operator, $value);
                                }
                            });
                        } else {
                            $q->{$condition === 'or' ? 'orWhereHas' : 'whereHas'}($relationPath, function ($subQuery) use ($column, $operator, $value) {
                                $this->applyAttributeFilter($subQuery, $column, $operator, $value);
                            });
                        }
                    } else {
                        $this->applyAttributeFilter($q, $column, $operator, $value, $condition === 'or');
                    }
                }
            });
        }

        return $query;
    }

    protected function applyAttributeFilter($query, $column, $operator, $value, $or = false): void
    {
        if (in_array($operator, ['<>', 'is_in']) && filled($dateRange = DateRange::tryFrom($value))) {
            ['start' => $startDate, 'end' => $endDate] = $dateRange->getDateRange();
            $query->{$or ? 'orWhereBetween' : 'whereBetween'}($column, [$startDate, $endDate]);
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
            $query->{$or ? 'orWhere' : 'where'}($column, '>=', $date);
        } elseif ($operator === 'before' && is_array($value)) {
            $date = Carbon::now();
            $amount = (int) ($value['amount'] ?? 0);
            $unit = $value['unit'] ?? 'days';
            match ($unit) {
                'weeks' => $date->addWeeks($amount),
                'months' => $date->addMonths($amount),
                'years' => $date->addYears($amount),
                default => $date->addDays($amount),
            };
            $query->{$or ? 'orWhere' : 'where'}($column, '<=', $date);
        } elseif ($operator === 'is_before') {
            $query->{$or ? 'orWhere' : 'where'}($column, '<', $value);
        } elseif ($operator === 'is_after') {
            $query->{$or ? 'orWhere' : 'where'}($column, '>', $value);
        } elseif (in_array($operator, ['in', 'not_in']) && is_array($value)) {
            $query->{$or ? ($operator === 'in' ? 'orWhereIn' : 'orWhereNotIn') : ($operator === 'in' ? 'whereIn' : 'whereNotIn')}($column, $value);
        } elseif ($operator === 'like') {
            $query->{$or ? 'orWhere' : 'where'}($column, 'LIKE', "%$value%");
        } else {
            $query->{$or ? 'orWhere' : 'where'}($column, $operator, $value);
        }
    }

    protected function applyUserFilter(Builder $query, string $attribute, $user): void
    {
        $modelClass = $this->exportSchedule->exporter::getModel();
        if (str_contains($attribute, '.')) {
            $parts = explode('.', $attribute);
            $column = array_pop($parts);
            $relationPath = implode('.', $parts);
            $query->whereHas($relationPath, fn($q) => $q->where($column, $user->getKey()));
        } elseif (method_exists($modelClass, $attribute) && ((new $modelClass)->$attribute()) instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
            $query->whereHas($attribute, fn($q) => $q->where($q->getModel()->getKeyName(), $user->getKey()));
        } else {
            $query->where($attribute, $user->getKey());
        }
    }

    public function run(): bool
    {
        if ($this->exportSchedule->isSqlQuery()) {
            return $this->initSqlQuery() && $this->buildSqlQueryJobChain();
        }

        if ($this->exportSchedule->dynamic_owner_enabled && $this->exportSchedule->dynamic_owner_attribute) {
            $baseQuery = $this->buildBaseQuery();
            $owners = $baseQuery->get()
                ->map(fn($m) => data_get($m, $this->exportSchedule->dynamic_owner_attribute))
                ->filter()
                ->unique(fn($u) => $u->getKey())
                ->values();

            foreach ($owners as $owner) {
                $this->runUser = $owner;
                $this->query = clone $baseQuery;
                if (! $this->init()) {
                    continue;
                }
                $this->buildJobChain();
            }

            return true;
        }

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
            $this->query = $this->buildBaseQuery();

            if ($this->runUser && $this->exportSchedule->dynamic_owner_attribute) {
                $this->applyUserFilter($this->query, $this->exportSchedule->dynamic_owner_attribute, $this->runUser);
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
            $export->user()->associate($this->runUser ?? $this->exportSchedule->owner);
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

    protected function initSqlQuery(): bool
    {
        try {
            $this->exportSchedule->loadMissing('owner');

            $errors = ExportSchedule::validateSqlQuery($this->exportSchedule->sql_query ?? '');
            if (! empty($errors)) {
                Log::error('SQL query validation failed', [
                    'schedule_id' => $this->exportSchedule->id,
                    'errors' => $errors,
                ]);

                return false;
            }

            // Count rows by wrapping in a subquery
            $countSql = "SELECT COUNT(*) as total FROM ({$this->exportSchedule->sql_query}) as subquery";
            $totalRows = DB::select($countSql)[0]->total ?? 0;

            $export = new Export;
            $export->exporter = 'sql_query';
            $export->total_rows = $totalRows;
            $export->file_disk = config('export-scheduler.file_disk');
            $export->file_name = $this->generateFileName();
            $export->user()->associate($this->exportSchedule->owner);
            $export->save();
            $this->export = $export;

            return true;
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());

            return false;
        }
    }

    public function buildSqlQueryJobChain(): bool
    {
        try {
            $formats = $this->exportSchedule->formats;
            $hasXlsx = in_array(ExportFormat::Xlsx, $formats);

            $this->export->unsetRelation('user');

            $makeCreateXlsxFileJob = fn(): CreateXlsxFile => app(CreateXlsxFile::class, [
                'export' => $this->export,
                'columnMap' => [],
                'options' => [],
            ]);

            Bus::chain([
                Bus::batch([
                    new ExportSqlQuery(
                        export: $this->export,
                        sql: $this->exportSchedule->sql_query,
                    ),
                ])->allowFailures(),

                // Conditional: CreateXlsxFile if XLSX format is requested
                ...($hasXlsx ? [$makeCreateXlsxFileJob()] : []),

                new ScheduledExportCompletion(
                    export: $this->export,
                    exportSchedule: $this->exportSchedule,
                ),
            ])->dispatch();

            return true;
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());

            return false;
        }
    }

    public function buildJobChain(): bool
    {
        try {
            $formats = $this->exportSchedule->formats;
            $hasXlsx = $formats->contains(ExportFormat::Xlsx);
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
