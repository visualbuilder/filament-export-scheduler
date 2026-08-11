<?php

namespace Visualbuilder\ExportScheduler\Filament\Actions;

use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use Visualbuilder\ExportScheduler\Contracts\BypassesReportVisibility;
use Visualbuilder\ExportScheduler\Models\ScheduledReport;
use Visualbuilder\ExportScheduler\Services\ScheduledExporter;

/**
 * Runs a schedule now: same query, same recipient, same cc list as its next
 * automatic run would use.
 *
 * Mounted only against a {@see ScheduledReport}. A report is not runnable in its
 * own right — it may carry several schedules with different recipients, so
 * "run this report" has no single meaning. The report-level equivalent is
 * Download, which is ad hoc and returns the file to whoever asked for it.
 */
trait RunExportTrait
{
    protected function setupRunExportAction()
    {
        $this
            ->icon('heroicon-s-play')
            ->color('success')
            ->visible(fn (ScheduledReport $record): bool => $record->report?->isOwnedBy(auth()->user())
                || app(BypassesReportVisibility::class)->can(auth()->user()))
            ->requiresConfirmation(fn (ScheduledReport $record) => $record->willLogoutUser())
            ->modalHeading(fn (ScheduledReport $record) => $record->willLogoutUser()
                ? __('export-scheduler::scheduler.run_modal_heading')
                : false)
            ->modalDescription(fn (ScheduledReport $record) => $record->willLogoutUser()
                ? new HtmlString("<p style='line-height: 2'>" . __('export-scheduler::scheduler.logout_warning') . '</p>')
                : false)
            ->modalSubmitActionLabel(fn (ScheduledReport $record) => $record->willLogoutUser()
                ? __('export-scheduler::scheduler.run_export')
                : false)
            ->modalFooterActionsAlignment(Alignment::End)
            ->action(function (ScheduledReport $record) {
                $this->runExportAction($record);
            });
    }

    protected function runExportAction(ScheduledReport $record): void
    {
        $report = $record->report;

        if (! $report) {
            Notification::make()
                ->title(__('export-scheduler::scheduler.download_failed_title'))
                ->body(__('export-scheduler::scheduler.download_failed_body'))
                ->danger()
                ->send();

            return;
        }

        $exporter = new ScheduledExporter($report, $record);
        $exporter->run();

        $ccCount = $record->cc_count;

        Notification::make()
            ->title(__('export-scheduler::scheduler.notification_title', ['name' => $report->name]))
            ->body(trans_choice('export-scheduler::scheduler.started.body', $ccCount, [
                'count' => Number::format($exporter->getTotalRows()),
                'cc_count' => $ccCount,
            ]))
            ->success()
            ->send();
    }
}
