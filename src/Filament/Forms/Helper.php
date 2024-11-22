<?php

namespace VisualBuilder\ExportScheduler\Filament\Forms;

use Filament\Forms\Get;
use VisualBuilder\ExportScheduler\Enums\ScheduleFrequency;

class Helper
{
    public static function isDayOfMonthFieldRequired(Get $get): bool
    {
        return in_array(
            $get('schedule_frequency'),
            [
                ScheduleFrequency::MONTHLY->value,
                ScheduleFrequency::QUARTERLY->value,
                ScheduleFrequency::HALF_YEARLY->value,
                ScheduleFrequency::YEARLY->value,
            ]
        );
    }

    public static function isStartDateRequired(Get $get): bool
    {
        return in_array(
            $get('schedule_frequency'),
            [

                ScheduleFrequency::QUARTERLY->value,
                ScheduleFrequency::HALF_YEARLY->value,
            ]
        );
    }
}
