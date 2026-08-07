<?php

namespace Visualbuilder\ExportScheduler\Mail;

use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Visualbuilder\ExportScheduler\Models\ExportSchedule;

class ExportReady extends Mailable
{
    use Queueable;
    use SerializesModels;

    public $name;

    public $url;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(public $notifiable, public Export $export, public ExportSchedule $exportSchedule)
    {
        $this->url = route('filament.exports.download', ['export' => $export, 'format' => $this->resolveFormat()]);
    }

    /**
     * Link to the spreadsheet when one was actually written, otherwise the CSV.
     *
     * The schedule's formats cannot be trusted here, as an export may be run on demand
     * for a single format.
     */
    protected function resolveFormat(): ExportFormat
    {
        $xlsxPath = $this->export->getFileDirectory() . DIRECTORY_SEPARATOR . $this->export->file_name . '.xlsx';

        return $this->export->getFileDisk()->exists($xlsxPath)
            ? ExportFormat::Xlsx
            : ExportFormat::Csv;
    }

    public function build()
    {
        return $this->subject("Download your {$this->exportSchedule->name}")
            ->to($this->notifiable->email ?? $this->exportSchedule->owner->email)
            ->view('export-scheduler::emails.export-ready')
            ->with([
                'user' => $this->notifiable,
                'url' => $this->url,
                'export' => $this->export,
                'exportSchedule' => $this->exportSchedule,
            ]);
    }
}
