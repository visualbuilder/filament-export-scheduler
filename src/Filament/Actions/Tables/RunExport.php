<?php

namespace Visualbuilder\ExportScheduler\Filament\Actions\Tables;


use Filament\Actions\Action;
use Visualbuilder\ExportScheduler\Filament\Actions\RunExportTrait;


class RunExport extends Action
{
    use RunExportTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupRunExportAction();
    }
}
