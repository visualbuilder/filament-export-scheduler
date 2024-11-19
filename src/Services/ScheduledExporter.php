<?php

namespace VisualBuilder\ExportScheduler\Services;

use AnourValar\EloquentSerialize\Facades\EloquentSerializeFacade;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Jobs\CreateXlsxFile;
use Filament\Actions\Exports\Jobs\PrepareCsvExport;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Bus\PendingChain;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use VisualBuilder\ExportScheduler\Jobs\ScheduledExportCompletion;
use VisualBuilder\ExportScheduler\Models\ExportSchedule;

class ScheduledExporter
{
    public function runExport(ExportSchedule $exportSchedule)
    {
        $exporter = $exportSchedule->exporter;

        // Get the query from the exporter class
        $query = $exporter::getModel()::query();
        $query = $exporter::modifyQuery($query);

        // Apply custom date range filter if available
        if ($exportSchedule->date_range) {
            $dateColumn = method_exists($exporter, 'getDateColumn')
                    ? $exporter::getDateColumn()
                    : 'created_at'; // Default to 'created_at' if method doesn't exist

            ['start' => $startDate, 'end' => $endDate] = $exportSchedule->date_range->getDateRange();
            $query->whereBetween($dateColumn, [$startDate, $endDate]);
        }

        // Prepare column mappings
        $columnMap = [];
        foreach ($exportSchedule->columns as $column) {
            $columnMap[$column['name']] = $column['label'] ?? $column['name'];
        }

        // Prepare options if needed
        $options = [];

        // Create Export instance
        $export = new Export;
        $export->exporter = $exporter;
        $export->total_rows = $query->count();
        $export->file_disk = config('export-scheduler.file_disk');
        $export->file_name = $this->generateFileName($exportSchedule);
        $export->user()->associate($exportSchedule->owner);
        $export->save();

        $exporterInstance = $export->getExporter(
            columnMap: $columnMap,
            options: $options,
        );

        $formats = $exportSchedule->formats;
        $hasXlsx = in_array(ExportFormat::Xlsx, $formats);

        $serializedQuery = EloquentSerializeFacade::serialize($query);

        /**
         * Enhancement add queue, connection and batchName to ExportSchedule
         * Maybe not needed as can be set in the Exporter by a dev.
         */
        $job = PrepareCsvExport::class;
        $jobQueue = $exporterInstance->getJobQueue();
        $jobConnection = $exporterInstance->getJobConnection();
        $jobBatchName = $exporterInstance->getJobBatchName();

        // We do not want to send the loaded user relationship to the queue in job payloads,
        // in case it contains attributes that are not serializable, such as binary columns.
        $export->unsetRelation('user');

        $makeCreateXlsxFileJob = fn (): CreateXlsxFile => app(CreateXlsxFile::class, [
            'export' => $export,
            'columnMap' => $columnMap,
            'options' => $options,
        ]);

        Bus::chain([
            // 1. Batch Job: Processes the export data (CSV).
            Bus::batch([app($job, [
                'export' => $export,
                'query' => $serializedQuery,
                'columnMap' => $columnMap,
                'options' => $options,
                'chunkSize' => 100,
                'records' => null,
            ])])
                ->when(filled($jobQueue), fn (PendingBatch $batch) => $batch->onQueue($jobQueue))
                ->when(filled($jobConnection), fn (PendingBatch $batch) => $batch->onConnection($jobConnection))
                ->when(filled($jobBatchName), fn (PendingBatch $batch) => $batch->name($jobBatchName))
                ->allowFailures(),

            // 2. Conditional Job: CreateXlsxFile if XLSX format is requested.
            ...($hasXlsx ? [$makeCreateXlsxFileJob()] : []),

            // 3. ScheduledExportCompletion Job: Marks export as complete after all files are ready.
            new ScheduledExportCompletion(
                export: $export,
                exportSchedule: $exportSchedule,
            ),
        ])
            ->when(filled($jobQueue), fn (PendingChain $chain) => $chain->onQueue($jobQueue))
            ->when(filled($jobConnection), fn (PendingChain $chain) => $chain->onConnection($jobConnection))
            ->dispatch();

        return $export->file_name;

    }

    protected function generateFileName(ExportSchedule $exportSchedule): string
    {
        return Str::slug($exportSchedule->name . '_' . now()->format('Y-m-d_Hi'));
    }
}
