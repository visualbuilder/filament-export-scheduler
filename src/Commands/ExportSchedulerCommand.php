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
        ExportSchedule::query()
            ->enabled()
            ->nextRunDue()
            ->each(function (ExportSchedule $exportSchedule) {
                // Attempt to run the export
                try {
                    (new ScheduledExporter($exportSchedule))->run();

                    fwrite(STDOUT, "Run Success, next due: ".$exportSchedule->calculateNextRun());
                    $exportSchedule->update([
                        'next_run_at' => $exportSchedule->calculateNextRun(),
                        'last_run_at' => now(),
                        'last_successful_run_at' => now(),
                    ]);
                } catch (Exception $e) {
                    $exportSchedule->update([
                        'last_run_at' => now(),
                    ]);

                    Log::error('Export failed', [
                        'schedule_id' => $exportSchedule->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });

        return self::SUCCESS;
    }
}
