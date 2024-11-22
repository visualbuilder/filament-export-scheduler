<?php

namespace VisualBuilder\ExportScheduler\Filament\Actions;

use Filament\Actions\Action;

class RunExport extends Action
{
    use RunExportTrait;
    protected function setUp(): void
    {
        parent::setUp();
        $this->setupRunExportAction();
    }
}
