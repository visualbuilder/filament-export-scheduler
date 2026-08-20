<?php

namespace Visualbuilder\ExportScheduler\Tests\Exporters;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use RuntimeException;
use Visualbuilder\ExportScheduler\Contracts\HasLinkedColumns;
use Visualbuilder\ExportScheduler\Tests\Models\Document;

/**
 * A test exporter whose link resolver throws for one specific document, so the
 * per-resolver guard in ViewCustomReport can be exercised: one bad link must not
 * blank the whole report.
 */
class ThrowingLinkDocumentExporter extends Exporter implements HasLinkedColumns
{
    protected static ?string $model = Document::class;

    /**
     * The title of the document whose link resolver should throw.
     */
    public static string $failForTitle = 'Boom';

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id'),
            ExportColumn::make('title'),
        ];
    }

    public static function getColumnLinks(): array
    {
        return [
            'id' => function (Document $document) {
                if ($document->title === static::$failForTitle) {
                    throw new RuntimeException('Resolver blew up for ' . $document->title);
                }

                return "/documents/{$document->id}";
            },
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Export completed';
    }
}
