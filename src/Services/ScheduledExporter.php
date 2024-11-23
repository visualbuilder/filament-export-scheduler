<?php

namespace VisualBuilder\ExportScheduler\Services;

use AnourValar\EloquentSerialize\Facades\EloquentSerializeFacade;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Jobs\CreateXlsxFile;
use Filament\Actions\Exports\Jobs\PrepareCsvExport;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Bus\PendingBatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\PendingChain;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use VisualBuilder\ExportScheduler\Jobs\ScheduledExportCompletion;
use VisualBuilder\ExportScheduler\Models\ExportSchedule;

class ScheduledExporter
{
    protected ?Exporter $exporterInstance = null;
    protected ?Export $export = null;
    protected ?Builder $query = null;
    protected array $columnMap = [];
    protected array $options = [];

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
     *
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
                $dateColumn = method_exists($exporter, 'getDateColumn')
                    ? $exporter::getDateColumn()
                    : 'created_at'; // Default to 'created_at' if method doesn't exist

                ['start' => $startDate, 'end' => $endDate] = $this->exportSchedule->date_range->getDateRange();
                $this->query->whereBetween($dateColumn, [$startDate, $endDate]);
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
                options: $this->options,
            );

            return true;
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            return false;
        }
    }


    protected function generateFileName(): string
    {
        return Str::slug($this->exportSchedule->name.'_'.now()->format('Y-m-d_Hi'));
    }

    public function buildJobChain():bool
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
                'export'    => $this->export,
                'columnMap' => $this->columnMap,
                'options'   => $this->options,
            ]);

            Bus::chain([
                // 1. Batch Job: Processes the export data (CSV).
                Bus::batch([app($job, [
                    'export'    => $this->export,
                    'query'     => $serializedQuery,
                    'columnMap' => $this->columnMap,
                    'options'   => $this->options,
                    'chunkSize' => 100,
                    'records'   => null,
                ])])
                    ->when(filled($jobQueue), fn(PendingBatch $batch) => $batch->onQueue($jobQueue))
                    ->when(filled($jobConnection), fn(PendingBatch $batch) => $batch->onConnection($jobConnection))
                    ->when(filled($jobBatchName), fn(PendingBatch $batch) => $batch->name($jobBatchName))
                    ->allowFailures(),

                // 2. Conditional Job: CreateXlsxFile if XLSX format is requested.
                ...($hasXlsx ? [$makeCreateXlsxFileJob()] : []),

                // 3. ScheduledExportCompletion Job: Marks export as complete after all files are ready.
                new ScheduledExportCompletion(
                    export: $this->export,
                    exportSchedule: $this->exportSchedule,
                ),
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
