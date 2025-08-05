<?php

namespace Visualbuilder\ExportScheduler\Filament\Resources\ExportScheduleResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Visualbuilder\ExportScheduler\Filament\Actions\RunExport;
use Visualbuilder\ExportScheduler\Filament\Resources\ExportScheduleResource;

class EditExportSchedule extends EditRecord
{
    protected static string $resource = ExportScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            RunExport::make('run export'),

        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (blank($data['filters'] ?? null)) {
            $data['filters'] = null;
        }

        return $data;
    }
}
