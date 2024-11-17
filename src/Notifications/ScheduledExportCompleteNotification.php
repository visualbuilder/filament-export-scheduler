<?php

namespace VisualBuilder\ExportScheduler\Notifications;

use VisualBuilder\ExportScheduler\Models\ExportSchedule;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

// implements ShouldQueue

class ScheduledExportCompleteNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;


    /**
     * Create a new notification instance.
     */
    public function __construct(public Export $export, public ExportSchedule $exportSchedule)
    {

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

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $mailable=config('export-scheduler.mail.mailable');
        return app(AdminExportReady::class, ['admin' => $notifiable,'export' => $this->export,'exportSchedule' => $this->exportSchedule]);
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
            "actions" => [],
            "body" => 'Export Complete',
            "color" => null,
            "duration" => "persistent",
            "icon" => 'heroicon-o-arrow-down-tray',
            "iconColor" => 'success',
            "status" => null,
            "title" => "Export Complete",
            "view" => "filament-notifications::notification",
            "viewData" => [],
            "format" => "filament",
            "sent_via" => $this->via($notifiable),
        ];
    }
}
