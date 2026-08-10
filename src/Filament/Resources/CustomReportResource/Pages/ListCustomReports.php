<?php

namespace Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource;

class ListCustomReports extends ListRecords
{
    protected static string $resource = CustomReportResource::class;

    /**
     * The query is already scoped by CustomReportResource::getEloquentQuery(),
     * which applies visibleTo() to every page of the resource at once.
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
