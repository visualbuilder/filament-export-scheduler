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

        // Whoever builds a report owns it. Ownership is transferable afterwards,
        // but there is no path to creating one you do not own.
        if ($user = auth()->user()) {
            $data['owner_type'] = $user::class;
            $data['owner_id'] = $user->getKey();
        }

        return $data;
    }
}
