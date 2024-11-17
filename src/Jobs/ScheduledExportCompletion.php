<?php

namespace VisualBuilder\ExportScheduler\Jobs;

use App\Notifications\AdminScheduledExportCompleteNotification;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use VisualBuilder\ExportScheduler\Models\ExportSchedule;

class ScheduledExportCompletion implements ShouldQueue
{
    use Dispatchable;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param  array  $columnMap
     * @param  array  $formats
     * @param  array  $options
     */
    public function __construct(
        protected Export $export,
        protected ExportSchedule $exportSchedule,
    ) {}

    /**
     * Execute the job.
     */
    public function handle()
    {
        // Mark the export as completed
        $this->export->touch('completed_at');
        // Send the email notification
        $this->export->user->notify(new AdminScheduledExportCompleteNotification($this->export, $this->exportSchedule));

    }
}
