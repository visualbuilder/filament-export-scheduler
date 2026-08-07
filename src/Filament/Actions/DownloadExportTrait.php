<?php

namespace Visualbuilder\ExportScheduler\Filament\Actions;

use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Visualbuilder\ExportScheduler\Models\ExportSchedule;
use Visualbuilder\ExportScheduler\Services\ScheduledExporter;

/**
 * Runs a saved report on demand and gives the results back to whoever asked for them.
 *
 * The export goes through the same queued pipeline as a scheduled run, so the file is
 * delivered by the usual notification, or offered straight away when the queue is sync.
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
            ->visible(fn (ExportSchedule $record): bool => $this->canDownload($record))
            ->schema([
                Radio::make('format')
                    ->label(__('export-scheduler::scheduler.download_format'))
                    ->options([
                        ExportFormat::Csv->value => __('export-scheduler::scheduler.CSV'),
                        ExportFormat::Xlsx->value => __('export-scheduler::scheduler.XLSX'),
                    ])
                    ->default(fn (ExportSchedule $record): string => $this->getDefaultFormat($record))
                    ->required(),
            ])
            ->action(function (array $data, ExportSchedule $record): void {
                $this->downloadExportAction($record, ExportFormat::from($data['format']));
            });
    }

    protected function downloadExportAction(ExportSchedule $record, ExportFormat $format): void
    {
        $exporter = (new ScheduledExporter($record))
            ->forUser(auth()->user())
            ->withFormats([$format]);

        if (! $exporter->run()) {
            Notification::make()
                ->title(__('export-scheduler::scheduler.download_failed_title'))
                ->body(__('export-scheduler::scheduler.download_failed_body'))
                ->danger()
                ->send();

            return;
        }

        $export = $exporter->getExport()?->fresh();

        $notification = Notification::make()
            ->title(__('export-scheduler::scheduler.download_started_title', ['name' => $record->name]))
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

    protected function canDownload(ExportSchedule $record): bool
    {
        if ($record->isSqlQuery()) {
            return filled($record->sql_query) && empty(ExportSchedule::validateSqlQuery($record->sql_query));
        }

        return filled($record->exporter) && class_exists($record->exporter);
    }

    protected function getDefaultFormat(ExportSchedule $record): string
    {
        $format = collect($record->formats ?? [])
            ->map(fn ($format) => $format instanceof ExportFormat ? $format->value : (string) $format)
            ->first();

        return ExportFormat::tryFrom((string) $format)?->value ?? ExportFormat::Csv->value;
    }
}
