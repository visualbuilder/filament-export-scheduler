<?php

namespace VisualBuilder\ExportScheduler\Notifications;

use Filament\Actions\Exports\Models\Export;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use VisualBuilder\ExportScheduler\Models\ExportSchedule;

// implements ShouldQueue

class ScheduledExportCompleteNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Export $export, public ExportSchedule $exportSchedule) {}

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return MailMessage
     */
    public function toMail($notifiable)
    {
        $mailableClass = config('export-scheduler.mailable');

        if (! class_exists($mailableClass)) {
            throw new InvalidArgumentException("The configured mailable class [{$mailableClass}] does not exist.");
        }
        Log::info('Sending Email');
        return new $mailableClass($notifiable, $this->export, $this->exportSchedule);
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'actions' => [],
            'body' => 'Scheduled Export Complete',
            'color' => null,
            'duration' => 'persistent',
            'icon' => 'heroicon-o-arrow-down-tray',
            'iconColor' => 'success',
            'status' => null,
            'title' => 'Scheduled Export Complete',
            'view' => 'filament-notifications::notification',
            'viewData' => [],
            'format' => 'filament',
            'sent_via' => $this->via($notifiable),
        ];
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database', 'mail'];
    }
}
