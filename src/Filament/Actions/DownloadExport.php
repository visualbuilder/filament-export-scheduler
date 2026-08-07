<?php

namespace Visualbuilder\ExportScheduler\Filament\Actions;

use Filament\Actions\Action;

class DownloadExport extends Action
{
    use DownloadExportTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupDownloadExportAction();
    }
}
