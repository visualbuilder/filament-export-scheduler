<?php

namespace VisualBuilder\ExportScheduler\Tests\Exporters;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use VisualBuilder\ExportScheduler\Tests\Models\Document;

class DocumentExporter extends Exporter
{
    protected static ?string $model = Document::class;

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Export completed';
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id'),
            ExportColumn::make('title'),
        ];
    }
}
