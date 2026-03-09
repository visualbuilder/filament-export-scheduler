<?php

namespace Visualbuilder\ExportScheduler\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReportType: string implements HasLabel
{
    case EXPORTER = 'exporter';
    case SQL_QUERY = 'sql_query';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::EXPORTER => 'Exporter',
            self::SQL_QUERY => 'SQL Query',
        };
    }
}
