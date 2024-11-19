<?php

namespace VisualBuilder\ExportScheduler\Filament\Resources\ExportScheduleResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use VisualBuilder\ExportScheduler\Filament\Resources\ExportScheduleResource;

class EditExportSchedule extends EditRecord
{
    protected static string $resource = ExportScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
