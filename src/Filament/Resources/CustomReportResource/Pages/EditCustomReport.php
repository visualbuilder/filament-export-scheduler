<?php

namespace Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource;

class EditCustomReport extends EditRecord
{
    protected static string $resource = CustomReportResource::class;

    /**
     * No Run and no Schedule button here. Running belongs to an individual
     * schedule, and scheduling lives in the schedules relation manager below the
     * form, whose create button is the Schedule action.
     */
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
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
