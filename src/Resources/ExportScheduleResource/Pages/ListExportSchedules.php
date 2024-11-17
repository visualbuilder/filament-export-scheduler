<?php

namespace VisualBuilder\ExportScheduler\Resources\ExportScheduleResource\Pages;

use VisualBuilder\ExportScheduler\Resources\ExportScheduleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExportSchedules extends ListRecords
{
    protected static string $resource = ExportScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
