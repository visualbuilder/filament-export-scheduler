<?php

namespace VisualBuilder\ExportScheduler\Filament\Forms;

use Filament\Forms\Get;
use Illuminate\Database\Eloquent\Relations\Relation;
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

    public static function isBooleanCast($type): bool
    {
        return $type === 'boolean';
    }

    public static function isDateTimeCast($column, $type): bool
    {
        $segments = explode('.', $column);
        $lastSegment = array_pop($segments);

        return str_ends_with($lastSegment, '_at')
            || in_array($type, ['date', 'datetime', 'timestamp', 'immutable_date', 'immutable_datetime'])
            || str_starts_with($type, 'date:')
            || str_starts_with($type, 'datetime:')
            || str_starts_with($type, 'timestamp:');
    }

    public static function extractCastType($path, $baseClass): ?string
    {
        $model = new $baseClass;
        $segments = explode('.', $path);
        $lastKey = array_pop($segments);

        foreach ($segments as $segment) {
            if (! method_exists($model, $segment)) {
                return null; // invalid relation
            }

            $relation = $model->$segment();
            if (! $relation instanceof Relation) {
                return null; // not a valid eloquent relation
            }

            $model = $relation->getRelated();
        }

        $casts = $model->getCasts();

        return $casts[$lastKey] ?? null;
    }

    public static function extractEnumCast($path, $baseClass): ?string
    {
        $model = new $baseClass;
        $segments = explode('.', $path);
        $lastKey = array_pop($segments);

        foreach ($segments as $segment) {
            if (!method_exists($model, $segment)) {
                return false; // invalid relation
            }

            $relation = $model->$segment();
            if (!$relation instanceof Relation) {
                return false; // not a valid eloquent relation
            }

            $model = $relation->getRelated();
        }

        $casts = $model->getCasts();
        $castType = $casts[$lastKey] ?? null;

        if ($castType && enum_exists($castType)) {
            return $castType;
        }

        return null;
    }
}
