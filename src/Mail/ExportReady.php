<?php

namespace VisualBuilder\ExportScheduler\Mail;

use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use VisualBuilder\ExportScheduler\Models\ExportSchedule;

class ExportReady extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($notifiable, public Export $export, public ExportSchedule $exportSchedule)
    {
        $hasXlsx = in_array(ExportFormat::Xlsx, $exportSchedule->formats);
        $url = route('filament.exports.download', ['export' => $export, 'format' => $hasXlsx ? ExportFormat::Xlsx : ExportFormat::Csv]);
        $this->sendTo = $notifiable->email;
        $this->export->url = $url;

    }
}
