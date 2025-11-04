<?php

namespace Visualbuilder\ExportScheduler\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Visualbuilder\ExportScheduler\Models\ExportSchedule;
use Visualbuilder\ExportScheduler\Services\ScheduledExporter;

class ExportSchedulerCommand extends Command
{
    public $signature = 'export:run';

    public $description = 'Runs scheduled exports';

    public function handle(): int
    {
        ExportSchedule::query()
            ->enabled()
            ->where(fn ($q) => $q
                ->nextRunDue()
                ->orWhereNull('next_run_at')
            )
            ->each(function (ExportSchedule $exportSchedule) {
                // For schedules with null next_run_at, check if it should run now
                if (is_null($exportSchedule->next_run_at) && !$exportSchedule->shouldRunNow()) {
                    return;
                }

                // Attempt to run the export
                try {
                    (new ScheduledExporter($exportSchedule))->run();
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
