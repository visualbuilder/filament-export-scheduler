<?php

namespace Visualbuilder\ExportScheduler\Jobs;

use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Models\Export;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Visualbuilder\ExportScheduler\Contracts\ResolvesReportUsers;
use Visualbuilder\ExportScheduler\Models\CustomReport;
use Visualbuilder\ExportScheduler\Models\ScheduledReport;

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
     * @param  array<string>  $formats  The files this run produced, used to offer a download link
     *                                  on an ad hoc run.
     * @param  string|null  $authGuard  Resolved when the chain is dispatched — filament() is not
     *                                  available to a queue worker.
     */
    public function __construct(
        protected Export $export,
        protected CustomReport $report,
        protected ?ScheduledReport $schedule = null,
        protected bool $isAdHoc = false,
        protected array $formats = [],
        protected ?string $authGuard = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle()
    {
        $this->export->touch('completed_at');

        // Skip notification if export is empty and send_empty_report is false
        $sendEmptyReport = $this->schedule?->send_empty_report ?? true;
        if (! $this->isAdHoc && $this->export->total_rows === 0 && ! $sendEmptyReport) {
            return;
        }

        // Somebody pulled their own copy from the panel. That is the same gesture as
        // exporting a table, so it lands in the notification bell with a download
        // link — not in their inbox. Email is for deliveries they did not ask for.
        if ($this->isAdHoc) {
            $this->notifyAdHocDownloadReady();

            return;
        }

        $notificationClass = config('export-scheduler.notification');

        // Check if the user object exists and uses the Notifiable trait
        if ($this->export->user && in_array(Notifiable::class, class_uses_recursive($this->export->user))) {
            if (class_exists($notificationClass)) {
                // The user can be notified
                $this->export->user->notify(new $notificationClass($this->export, $this->report, $this->schedule));

                // Clone the Export for each cc'd user. Only reachable for a scheduled
                // run — an ad hoc download returned above and never has cc recipients.
                if ($this->schedule && $this->schedule->cc && is_array($this->schedule->cc) && count($this->schedule->cc)) {
                    $resolver = app(ResolvesReportUsers::class);

                    foreach ($this->schedule->cc as $userId) {
                        if (! filled($userId)) {
                            continue;
                        }

                        $ccUser = $resolver->find($this->schedule->recipient_type, $userId);
                        if (! $ccUser) {
                            continue;
                        }

                        $copiedExport = $this->export->replicate(['user_id', 'url']);
                        $copiedExport->user_type = $this->schedule->recipient_type;
                        $copiedExport->user_id = $userId;
                        $copiedExport->save();
                        $this->copyExportFiles($this->export, $copiedExport);
                        $copiedExport->load('user');

                        if ($copiedExport->user) {
                            $copiedExport->user->notify(new $notificationClass($copiedExport, $this->report, $this->schedule));
                        }
                    }
                }
            } else {
                Log::error('Notification class does not exist. Check config/export-scheduler.php.', [
                    'class' => $notificationClass,
                    'user_id' => $this->export->user->id,
                ]);
            }
        } else {
            Log::error('Attempted to notify a user that does not use the Notifiable trait or user is null.', [
                'user_id' => $this->export->user->id ?? null,
            ]);
        }
    }

    /**
     * The download-link notification, matching what Filament's own table exports
     * send. Written straight to the database so it survives the queue round trip
     * and appears in the bell, rather than being flashed into a request that
     * finished long ago.
     */
    protected function notifyAdHocDownloadReady(): void
    {
        $user = $this->export->user;

        if (! $user) {
            Log::error('Ad hoc export completed with no user to notify.', [
                'export_id' => $this->export->getKey(),
            ]);

            return;
        }

        $formats = collect($this->formats)
            ->map(fn ($format) => $format instanceof ExportFormat ? $format : ExportFormat::tryFrom((string) $format))
            ->filter()
            ->values();

        $guard = $this->authGuard ?? config('filament.auth.guard') ?? config('auth.defaults.guard');

        Notification::make()
            ->title(__('export-scheduler::scheduler.download_complete_title', ['name' => $this->report->name]))
            ->body(__('export-scheduler::scheduler.download_complete_body'))
            ->success()
            ->icon('heroicon-o-arrow-down-tray')
            ->actions($formats
                ->map(fn (ExportFormat $format) => $format->getDownloadNotificationAction($this->export, $guard))
                ->all())
            ->sendToDatabase($user, isEventDispatched: true);
    }

    protected function copyExportFiles(Export $source, Export $destination): void
    {
        $sourceDisk = $source->getFileDisk();
        $sourceDirectory = $source->getFileDirectory();

        if (! $sourceDisk->exists($sourceDirectory)) {
            return;
        }

        $destinationDisk = $destination->getFileDisk();
        $destinationDirectory = $destination->getFileDirectory();

        foreach ($sourceDisk->files($sourceDirectory) as $file) {
            $destinationDisk->writeStream(
                $destinationDirectory . DIRECTORY_SEPARATOR . basename($file),
                $sourceDisk->readStream($file)
            );
        }
    }
}
