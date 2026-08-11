<?php

namespace Visualbuilder\ExportScheduler\Support;

use Illuminate\Database\Eloquent\Model;
use Visualbuilder\ExportScheduler\Contracts\BypassesReportVisibility;

class VisibilityBypass implements BypassesReportVisibility
{
    public function can(?Model $user): bool
    {
        return false;
    }
}
