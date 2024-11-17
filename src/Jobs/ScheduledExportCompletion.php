<?php
namespace VisualBuilder\ExportScheduler\Jobs;

use VisualBuilder\ExportScheduler\Models\ExportSchedule;
use App\Notifications\AdminScheduledExportCompleteNotification;
use  VisualBuilder\ExportScheduler\Services\DynamicExporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScheduledExportCompletion implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param Export $export
     * @param array $columnMap
     * @param array $formats
     * @param array $options
     * @param ExportSchedule $exportSchedule
     */
    public function __construct(
        protected Export $export,
        protected ExportSchedule $exportSchedule,
    ) {

    }

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
