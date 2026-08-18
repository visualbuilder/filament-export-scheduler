<?php

namespace Visualbuilder\ExportScheduler\Tests\Exporters;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Visualbuilder\ExportScheduler\Contracts\HasLinkedColumns;
use Visualbuilder\ExportScheduler\Tests\Models\Document;

class LinkedDocumentExporter extends Exporter implements HasLinkedColumns
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

    public static function getColumnLinks(): array
    {
        return [
            'id' => fn (Document $document) => "/documents/{$document->id}",
            'owner.created_at' => fn (Document $document) => $document->owner
                ? "/users/{$document->owner->id}"
                : null,
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Export completed';
    }
}
