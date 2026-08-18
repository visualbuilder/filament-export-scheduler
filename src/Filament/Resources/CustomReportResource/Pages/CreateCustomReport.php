<?php

namespace Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource;

class CreateCustomReport extends CreateRecord
{
    protected static string $resource = CustomReportResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['filters'] ?? null)) {
            $data['filters'] = null;
        }

        // The form pre-fills the owner with whoever is building the report, but it
        // is editable: a report may be created on someone else's behalf and handed
        // to them. Only fall back when the form left it blank, so that choice is
        // never silently overwritten.
        if (blank($data['owner_type'] ?? null) || blank($data['owner_id'] ?? null)) {
            if ($user = auth()->user()) {
                $data['owner_type'] = $user::class;
                $data['owner_id'] = $user->getKey();
            }
        }

        return $data;
    }
}
