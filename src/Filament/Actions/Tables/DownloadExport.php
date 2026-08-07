<?php

namespace Visualbuilder\ExportScheduler\Filament\Actions\Tables;

use Filament\Actions\Action;
use Visualbuilder\ExportScheduler\Filament\Actions\DownloadExportTrait;

class DownloadExport extends Action
{
    use DownloadExportTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupDownloadExportAction();
    }
}
