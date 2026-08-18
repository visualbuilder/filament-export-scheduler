<?php

namespace Visualbuilder\ExportScheduler\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Visualbuilder\ExportScheduler\ExportScheduler
 *
 * @method static bool isValidCronExpression(string $expression)
 * @method static array listExporters()
 */
class ExportScheduler extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Visualbuilder\ExportScheduler\ExportScheduler::class;
    }
}
