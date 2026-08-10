<?php

namespace Visualbuilder\ExportScheduler\Mail;

use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Visualbuilder\ExportScheduler\Contracts\ResolvesReportUsers;
use Visualbuilder\ExportScheduler\Models\CustomReport;
use Visualbuilder\ExportScheduler\Models\ScheduledReport;

class ExportReady extends Mailable
{
    use Queueable;
    use SerializesModels;

    public $url;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Export $export,
        public CustomReport $report,
        public ?ScheduledReport $schedule = null,
    ) {
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
        $resolver = app(ResolvesReportUsers::class);
        $userEmail = $resolver->getEmail($this->export->user) ?? $resolver->getEmail($this->report->owner);

        if (!$userEmail) {
            Log::warning('Cannot send export email: user has no configured email address', [
                'export_id' => $this->export->id,
                'user_type' => $this->export->user_type,
                'user_id' => $this->export->user_id,
            ]);
        }

        return $this->subject("Download your {$this->report->name}")
            ->to($userEmail)
            ->view('export-scheduler::emails.export-ready')
            ->with([
                'export' => $this->export,
                'report' => $this->report,
                'schedule' => $this->schedule,
            ]);
    }
}
