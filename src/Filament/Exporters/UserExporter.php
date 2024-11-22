<?php

namespace VisualBuilder\ExportScheduler\Filament\Exporters;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use VisualBuilder\ExportScheduler\Tests\Models\User;

class UserExporter extends Exporter
{
    protected static ?string $model = User::class;

    /**
     * Allow Setting a custom date attribute to use for filtering records
     */
    public static function getDateColumn(): string
    {
        return 'created_at';
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id'),
            ExportColumn::make('email'),
            ExportColumn::make('created_at')->label('Date Added'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your user export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
