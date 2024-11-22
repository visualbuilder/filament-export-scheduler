<?php

namespace VisualBuilder\ExportScheduler\Filament\Actions\Tables;

use Filament\Tables\Actions\Action;
use VisualBuilder\ExportScheduler\Filament\Actions\RunExportTrait;


class RunExport extends Action
{
    use RunExportTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupRunExportAction();
    }
}
