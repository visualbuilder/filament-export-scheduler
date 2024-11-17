<?php

namespace VisualBuilder\ExportScheduler\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \VisualBuilder\ExportScheduler\ExportScheduler
 */
class ExportScheduler extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \VisualBuilder\ExportScheduler\ExportScheduler::class;
    }
}
