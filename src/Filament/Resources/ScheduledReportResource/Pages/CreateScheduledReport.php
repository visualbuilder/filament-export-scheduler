<?php

namespace Visualbuilder\ExportScheduler\Filament\Resources\ScheduledReportResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Visualbuilder\ExportScheduler\Filament\Resources\ScheduledReportResource;
use Visualbuilder\ExportScheduler\Models\CustomReport;

class CreateScheduledReport extends CreateRecord
{
    protected static string $resource = ScheduledReportResource::class;

    /**
     * The picker only offers reports you own, but the form is not the only way in
     * — a crafted request must not be able to attach a schedule to someone
     * else's report.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $report = CustomReport::find($data['custom_report_id'] ?? null);

        if (! $report?->isOwnedBy(auth()->user())) {
            throw new AuthorizationException(
                'You can only schedule reports you own.'
            );
        }

        return $data;
    }
}
