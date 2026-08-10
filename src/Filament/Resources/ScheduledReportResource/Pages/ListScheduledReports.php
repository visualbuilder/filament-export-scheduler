<?php

namespace Visualbuilder\ExportScheduler\Filament\Resources\ScheduledReportResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Visualbuilder\ExportScheduler\Filament\Resources\ScheduledReportResource;

class ListScheduledReports extends ListRecords
{
    protected static string $resource = ScheduledReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('export-scheduler::scheduler.new_schedule')),
        ];
    }
}
