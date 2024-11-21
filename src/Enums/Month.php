<?php

namespace VisualBuilder\ExportScheduler\Enums;

use Carbon\Carbon;
use Filament\Support\Contracts\HasLabel;

enum Month: int implements HasLabel
{
    case JANUARY = 1;
    case FEBRUARY = 2;
    case MARCH = 3;
    case APRIL = 4;
    case MAY = 5;
    case JUNE = 6;
    case JULY = 7;
    case AUGUST = 8;
    case SEPTEMBER = 9;
    case OCTOBER = 10;
    case NOVEMBER = 11;
    case DECEMBER = 12;

    public function getLabel(): string
    {
        return match ($this) {
            self::JANUARY => __('export-scheduler::scheduler.january'), // lowercase
            self::FEBRUARY => __('export-scheduler::scheduler.february'), // lowercase
            self::MARCH => __('export-scheduler::scheduler.march'), // lowercase
            self::APRIL => __('export-scheduler::scheduler.april'), // lowercase
            self::MAY => __('export-scheduler::scheduler.may'), // lowercase
            self::JUNE => __('export-scheduler::scheduler.june'), // lowercase
            self::JULY => __('export-scheduler::scheduler.july'), // lowercase
            self::AUGUST => __('export-scheduler::scheduler.august'), // lowercase
            self::SEPTEMBER => __('export-scheduler::scheduler.september'), // lowercase
            self::OCTOBER => __('export-scheduler::scheduler.october'), // lowercase
            self::NOVEMBER => __('export-scheduler::scheduler.november'), // lowercase
            self::DECEMBER => __('export-scheduler::scheduler.december'), // lowercase
        };
    }

}
