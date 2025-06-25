<?php

namespace VisualBuilder\ExportScheduler\Filament\Resources\ExportScheduleResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use VisualBuilder\ExportScheduler\Filament\Resources\ExportScheduleResource;

class CreateExportSchedule extends CreateRecord
{
    protected static string $resource = ExportScheduleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['filters'] ?? null)) {
            $data['filters'] = null;
        }

        return $data;
    }
}
