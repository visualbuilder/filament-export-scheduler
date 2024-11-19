<?php

namespace VisualBuilder\ExportScheduler\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use VisualBuilder\ExportScheduler\Models\ExportSchedule;
use VisualBuilder\ExportScheduler\Services\ScheduledExporter;

class ExportSchedulerCommand extends Command
{
    public $signature = 'export:run';

    public $description = 'Runs scheduled exports';

    public function handle(): int
    {
        // Create an instance of the DynamicExporterService
        $scheduledExporter = new ScheduledExporter;

        ExportSchedule::enabled()->each(function (ExportSchedule $exportSchedule) use ($scheduledExporter) {
            // Skip if the export is not due
            if (!$exportSchedule->next_due_at || now()->lessThan($exportSchedule->next_due_at)) {
                $this->warn($exportSchedule->name.'next due at'.$exportSchedule->next_due_at);
                return;
            }

            // Attempt to run the export
            try {
                $this->info('Running '.$exportSchedule->name);
                $scheduledExporter->runExport($exportSchedule);

                $exportSchedule->update([
                    'last_run_at'            => now(),
                    'last_successful_run_at' => now(),
                ]);

                $this->alert('Finished '.$exportSchedule->name);
            } catch (Exception $e) {
                $exportSchedule->update([
                    'last_run_at' => now(),
                ]);

                Log::error('Export failed', [
                    'schedule_id' => $exportSchedule->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        });

        return self::SUCCESS;

    }
}
