<?php

namespace Visualbuilder\ExportScheduler\Jobs;

use Filament\Actions\Exports\Jobs\PrepareCsvExport as BasePrepareCsvExport;

class PrepareCsvExport extends BasePrepareCsvExport
{
     public function getExportCsvJob(): string
    {
        return ExportCsv::class;
    }
}
