<?php

namespace VisualBuilder\ExportScheduler\Tests\Exporters;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use VisualBuilder\ExportScheduler\Tests\Models\Document;

class DocumentOwnerExporter extends Exporter
{
    protected static ?string $model = Document::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id'),
            ExportColumn::make('title'),
            ExportColumn::make('owner.created_at')->label('Owner Created At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Export completed';
    }
}
