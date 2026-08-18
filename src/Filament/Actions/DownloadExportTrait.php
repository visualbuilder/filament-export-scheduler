<?php

namespace Visualbuilder\ExportScheduler\Filament\Actions;

use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Visualbuilder\ExportScheduler\Models\CustomReport;
use Visualbuilder\ExportScheduler\Models\ScheduledReport;
use Visualbuilder\ExportScheduler\Services\ScheduledExporter;

/**
 * Runs a saved report on demand and gives the results back to whoever asked for them.
 *
 * The export goes through the same queued pipeline as a scheduled run, so the file is
 * delivered by the usual notification, or offered straight away when the queue is sync.
 *
 * Unlike Run, this is available to anyone the report is visible to: forUser() makes the
 * resulting Export owned by, notified to and downloadable by the requester only, so a
 * shared report can never be used to fire a delivery at its schedule's recipient.
 */
trait DownloadExportTrait
{
    protected function setupDownloadExportAction(): void
    {
        $this
            ->label(__('export-scheduler::scheduler.download'))
            ->icon('heroicon-o-arrow-down-tray')
            ->color('primary')
            ->modalWidth(Width::Small)
            ->modalHeading(__('export-scheduler::scheduler.download_modal_heading'))
            ->modalSubmitActionLabel(__('export-scheduler::scheduler.download'))
            ->modalFooterActionsAlignment(Alignment::End)
            ->visible(fn (Model $record): bool => $this->canDownload($record))
            ->schema([
                Radio::make('format')
                    ->label(__('export-scheduler::scheduler.download_format'))
                    ->options([
                        ExportFormat::Csv->value => __('export-scheduler::scheduler.CSV'),
                        ExportFormat::Xlsx->value => __('export-scheduler::scheduler.XLSX'),
                    ])
                    ->default(fn (Model $record): string => $this->getDefaultFormat($record))
                    ->required(),
            ])
            ->action(function (array $data, Model $record): void {
                $this->downloadExportAction($record, ExportFormat::from($data['format']));
            });
    }

    protected function downloadExportAction(Model $record, ExportFormat $format): void
    {
        $report = $this->reportFor($record);

        if (! $report) {
            $this->notifyDownloadFailed();

            return;
        }

        // No schedule: an ad hoc download has no recipient, no cc and no
        // send_empty_report suppression.
        $exporter = (new ScheduledExporter($report))
            ->forUser(auth()->user())
            ->withFormats([$format]);

        if (! $exporter->run()) {
            $this->notifyDownloadFailed();

            return;
        }

        $export = $exporter->getExport()?->fresh();

        $notification = Notification::make()
            ->title(__('export-scheduler::scheduler.download_started_title', ['name' => $report->name]))
            ->success();

        // A sync queue has already finished the chain by now, so the file can be offered directly.
        if ($export?->completed_at) {
            $notification
                ->body(__('export-scheduler::scheduler.download_ready_body'))
                ->actions([
                    $format->getDownloadNotificationAction($export, filament()->getAuthGuard()),
                ]);
        } else {
            $notification->body(__('export-scheduler::scheduler.download_started_body'));
        }

        $notification->send();
    }

    protected function notifyDownloadFailed(): void
    {
        Notification::make()
            ->title(__('export-scheduler::scheduler.download_failed_title'))
            ->body(__('export-scheduler::scheduler.download_failed_body'))
            ->danger()
            ->send();
    }

    protected function canDownload(Model $record): bool
    {
        $report = $this->reportFor($record);

        if (! $report?->isVisibleTo(auth()->user())) {
            return false;
        }

        if ($report->isSqlQuery()) {
            return filled($report->sql_query) && empty(CustomReport::validateSqlQuery($report->sql_query));
        }

        return filled($report->exporter) && class_exists($report->exporter);
    }

    /**
     * Preselect the schedule's own format when downloading from a schedule row. A
     * report carries no format of its own, so downloading one offers XLSX.
     */
    protected function getDefaultFormat(Model $record): string
    {
        $format = collect($record instanceof ScheduledReport ? $record->resolved_formats : [])
            ->map(fn ($format) => $format instanceof ExportFormat ? $format->value : (string) $format)
            ->first();

        return ExportFormat::tryFrom((string) $format)?->value ?? ExportFormat::Xlsx->value;
    }

    protected function reportFor(Model $record): ?CustomReport
    {
        return match (true) {
            $record instanceof CustomReport => $record,
            $record instanceof ScheduledReport => $record->report,
            default => null,
        };
    }
}
