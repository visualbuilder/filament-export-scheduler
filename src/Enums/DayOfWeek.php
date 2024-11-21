<?php

namespace VisualBuilder\ExportScheduler\Enums;

use Carbon\Carbon;
use Filament\Support\Contracts\HasLabel;

enum DayOfWeek: int implements HasLabel
{
    use EnumSubset;

    case SUNDAY    = 0;
    case MONDAY    = 1;
    case TUESDAY   = 2;
    case WEDNESDAY = 3;
    case THURSDAY  = 4;
    case FRIDAY    = 5;
    case SATURDAY  = 6;


    public function getLabel(): string
    {
        return match ($this) {
            self::SUNDAY    => __('export-scheduler::scheduler.sunday'),
            self::MONDAY    => __('export-scheduler::scheduler.monday'),
            self::TUESDAY   => __('export-scheduler::scheduler.tuesday'),
            self::WEDNESDAY => __('export-scheduler::scheduler.wednesday'),
            self::THURSDAY  => __('export-scheduler::scheduler.thursday'),
            self::FRIDAY    => __('export-scheduler::scheduler.friday'),
            self::SATURDAY  => __('export-scheduler::scheduler.saturday'),
        };
    }

}
