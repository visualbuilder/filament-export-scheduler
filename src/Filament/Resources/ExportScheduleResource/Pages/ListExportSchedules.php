<?php

namespace VisualBuilder\ExportScheduler\Filament\Resources\ExportScheduleResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use VisualBuilder\ExportScheduler\Filament\Resources\ExportScheduleResource;

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
