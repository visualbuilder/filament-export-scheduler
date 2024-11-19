<?php

namespace VisualBuilder\ExportScheduler\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \VisualBuilder\ExportScheduler\ExportScheduler
 * @method static bool isValidCronExpression(string $expression)
 * @method static array listExporters()
 */
class ExportScheduler extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \VisualBuilder\ExportScheduler\ExportScheduler::class;
    }
}
