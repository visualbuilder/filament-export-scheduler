<?php

namespace VisualBuilder\ExportScheduler\Filament\Actions;

use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use VisualBuilder\ExportScheduler\Models\ExportSchedule;
use VisualBuilder\ExportScheduler\Services\ScheduledExporter;

trait RunExportTrait
{

    protected function setupRunExportAction()
    {
        $this
            ->icon('heroicon-s-play')
            ->color('success')
            ->requiresConfirmation(fn(ExportSchedule $record) => $record->willLogoutUser())
            ->modalHeading(fn(ExportSchedule $record) => $record->willLogoutUser() ? __("export-scheduler::scheduler.run_modal_heading") : false)
            ->modalDescription(fn(ExportSchedule $record) => $record->willLogoutUser()
                ? new HtmlString("<p style='line-height: 2'>".__('export-scheduler::scheduler.logout_warning')."</p>")
                : false)
            ->modalSubmitActionLabel(fn(ExportSchedule $record) => $record->willLogoutUser() ? __('export-scheduler::scheduler.run_export') : false)
            ->modalFooterActionsAlignment(Alignment::End)
            ->action(function ($record) {
                $this->runExportAction($record);
            });
    }

    protected function runExportAction($record)
    {
        $exporter = new ScheduledExporter($record);
        $exporter->run();
        Notification::make()
            ->title(__('export-scheduler::scheduler.notification_title', ['name' => $record->name]))
            ->body(trans_choice('export-scheduler::scheduler.started.body', $exporter->getTotalRows(), [
                'count' => Number::format($exporter->getTotalRows()),
            ]))
            ->success()
            ->send();
    }
}
