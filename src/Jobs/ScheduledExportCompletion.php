<?php

namespace Visualbuilder\ExportScheduler\Jobs;

use Filament\Actions\Exports\Models\Export;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Visualbuilder\ExportScheduler\Models\ExportSchedule;

class ScheduledExportCompletion implements ShouldQueue
{
    use Dispatchable;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param  bool  $isAdHoc  True when a user ran this export on demand for themselves, in which
     *                         case only they are notified and an empty report is never suppressed.
     */
    public function __construct(
        protected Export $export,
        protected ExportSchedule $exportSchedule,
        protected bool $isAdHoc = false,
    ) {}

    /**
     * Execute the job.
     */
    public function handle()
    {

        // Mark the export as completed
        $this->export->touch('completed_at');

        // Skip notification if export is empty and send_empty_report is false
        if (! $this->isAdHoc && $this->export->total_rows === 0 && ! $this->exportSchedule->send_empty_report) {
            return;
        }

        $notificationClass = config('export-scheduler.notification');

        // Check if the user object exists and uses the Notifiable trait
        if ($this->export->user && in_array(Notifiable::class, class_uses_recursive($this->export->user))) {

            if (class_exists($notificationClass)) {
                // The user can be notified
                $this->export->user->notify(new $notificationClass($this->export, $this->exportSchedule));

                // Clone the Export for each copied user, unless this was a one off download
                if (! $this->isAdHoc && $this->exportSchedule->cc && is_array($this->exportSchedule->cc) && count($this->exportSchedule->cc)) {
                    foreach ($this->exportSchedule->cc as $userId) {
                        if (! is_numeric($userId)) {
                            continue;
                        }
                        $copiedExport = $this->export->replicate(['user_id', 'url']);
                        $copiedExport->user_id = $userId;
                        $copiedExport->save();
                        $copiedExport->load('user');
                        if ($copiedExport->user) {
                            $copiedExport->user->notify(new $notificationClass($copiedExport, $this->exportSchedule));
                        }

                    }
                }

            } else {
                // Log error if the notification class does not exist
                Log::error('Notification class does not exist.  Check the config/export-scheduler.php to add a notification class.', [
                    'class' => $notificationClass,
                    'user_id' => $this->export->user->id,
                ]);
            }
        } else {
            // Log error if the user cannot be notified
            Log::error('Attempted to notify a user that does not use the Notifiable trait or user is null.', [
                'user_id' => $this->export->user->id ?? null,
            ]);
        }

    }
}
