<?php

namespace Visualbuilder\ExportScheduler\Filament\Resources\ScheduledReportResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource;
use Visualbuilder\ExportScheduler\Filament\Resources\ScheduledReportResource;

class EditScheduledReport extends EditRecord
{
    protected static string $resource = ScheduledReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewReport')
                ->label(__('export-scheduler::scheduler.view_report'))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn () => CustomReportResource::getUrl('view', ['record' => $this->record->report->getKey()]))
                ->openUrlInNewTab(),
            DeleteAction::make(),
        ];
    }
}
