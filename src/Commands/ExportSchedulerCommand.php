<?php

namespace Visualbuilder\ExportScheduler\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Visualbuilder\ExportScheduler\Models\ScheduledReport;
use Visualbuilder\ExportScheduler\Services\ScheduledExporter;

class ExportSchedulerCommand extends Command
{
    public $signature = 'export:run';

    public $description = 'Runs scheduled exports';

    public function handle(): int
    {
        ScheduledReport::query()
            ->with('report')
            ->enabled()
            ->where(
                fn ($q) => $q
                    ->nextRunDue()
                    ->orWhereNull('next_run_at')
            )
            ->each(function (ScheduledReport $schedule) {
                if (! $schedule->report) {
                    Log::warning('Scheduled report has no linked custom report', ['schedule_id' => $schedule->id]);

                    return;
                }

                // For schedules with null next_run_at, check if it should run now
                if (is_null($schedule->next_run_at) && ! $schedule->shouldRunNow()) {
                    return;
                }

                // Attempt to run the export
                try {
                    $ran = (new ScheduledExporter($schedule->report, $schedule))->run();

                    // run() catches its own failures and returns false, so a failed run
                    // keeps the previous last_successful_run_at rather than claiming one.
                    // next_run_at still advances, so a broken report is not retried every minute.
                    $attributes = [
                        'next_run_at' => $schedule->calculateNextRun(),
                        'last_run_at' => now(),
                    ];

                    if ($ran) {
                        $attributes['last_successful_run_at'] = now();
                    } else {
                        Log::error('Export failed', ['schedule_id' => $schedule->id]);
                    }

                    $schedule->update($attributes);
                } catch (Exception $e) {
                    $schedule->update([
                        'last_run_at' => now(),
                    ]);

                    Log::error('Export failed', [
                        'schedule_id' => $schedule->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });

        return self::SUCCESS;
    }
}
