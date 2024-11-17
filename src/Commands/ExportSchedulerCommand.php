<?php

namespace VisualBuilder\ExportScheduler\Commands;

use Illuminate\Console\Command;
use VisualBuilder\ExportScheduler\Models\ExportSchedule;
use VisualBuilder\ExportScheduler\Services\DynamicExporter;

class ExportSchedulerCommand extends Command
{
    public $signature = 'export:run';

    public $description = 'Runs scheduled exports';

    public function handle(): int
    {

        // Create an instance of the DynamicExporterService
        $dynamicExporter = new DynamicExporter;

        ExportSchedule::all()->each(function (ExportSchedule $exportSchedule) use ($dynamicExporter) {
            // Skip if the export is not due
            if (! $exportSchedule->isDue()) {
                return;
            }

            // Attempt to run the export
            try {
                $dynamicExporter->runExport($exportSchedule);

                // Update the last successful run time
                $exportSchedule->update([
                    'last_run_at' => now(),
                    'last_successful_run_at' => now(),
                ]);
            } catch (\Exception $e) {
                // Update only the last run time if it fails
                $exportSchedule->update([
                    'last_run_at' => now(),
                ]);

                // Optionally, log the failure
                \Log::error('Export failed', [
                    'schedule_id' => $exportSchedule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        return self::SUCCESS;

    }
}
