<?php

namespace Visualbuilder\ExportScheduler\Enums;

use Carbon\Carbon;
use Filament\Support\Contracts\HasLabel;

enum DateRange: string implements HasLabel
{
    use EnumSubset;

    case TODAY = 'today';
    case YESTERDAY = 'yesterday';
    case LAST_7_DAYS = 'last_7_days';
    case LAST_WEEK = 'last_week';
    case LAST_30_DAYS = 'last_30_days';
    case LAST_MONTH = 'last_month';
    case THIS_MONTH = 'this_month';
    case LAST_QUARTER = 'last_quarter';
    case THIS_YEAR = 'this_year';
    case LAST_YEAR = 'last_year';
    case NEXT_7_DAYS = 'next_7_days';
    case NEXT_30_DAYS = 'next_30_days';
    case NEXT_60_DAYS = 'next_60_days';
    case NEXT_90_DAYS = 'next_90_days';

    public function getLabel(): string
    {
        return __('export-scheduler::date_ranges.'.$this->value);
    }

    public function getDateRange(): array
    {
        $now = Carbon::now();

        return match ($this) {
            self::TODAY        => [
                'start' => $now->copy()->startOfDay(),
                'end'   => $now->copy()->endOfDay(),
            ],
            self::YESTERDAY    => [
                'start' => $now->copy()->subDay()->startOfDay(),
                'end'   => $now->copy()->subDay()->endOfDay(),
            ],
            self::LAST_7_DAYS  => [
                'start' => $now->copy()->subDays(6)->startOfDay(),
                'end'   => $now->copy()->endOfDay(),
            ],
            self::LAST_WEEK    => [
                'start' => $now->copy()->subWeek()->startOfWeek(),
                'end'   => $now->copy()->subWeek()->endOfWeek(),
            ],
            self::LAST_30_DAYS => [
                'start' => $now->copy()->subDays(29)->startOfDay(),
                'end'   => $now->copy()->endOfDay(),
            ],
            self::LAST_MONTH   => [
                'start' => $now->copy()->subMonth()->startOfMonth(),
                'end'   => $now->copy()->subMonth()->endOfMonth(),
            ],
            self::THIS_MONTH   => [
                'start' => $now->copy()->startOfMonth(),
                'end'   => $now->copy()->endOfMonth(),
            ],
            self::LAST_QUARTER => [
                'start' => $now->copy()->subQuarter()->firstOfQuarter(),
                'end'   => $now->copy()->subQuarter()->lastOfQuarter(),
            ],
            self::THIS_YEAR    => [
                'start' => $now->copy()->startOfYear(),
                'end'   => $now->copy()->endOfYear(),
            ],
            self::LAST_YEAR    => [
                'start' => $now->copy()->subYear()->startOfYear(),
                'end'   => $now->copy()->subYear()->endOfYear(),
            ],
            self::NEXT_7_DAYS  => [
                'start' => $now->copy()->startOfDay(),
                'end'   => $now->copy()->addDays(6)->endOfDay(),
            ],
            self::NEXT_30_DAYS => [
                'start' => $now->copy()->startOfDay(),
                'end'   => $now->copy()->addDays(29)->endOfDay(),
            ],
            self::NEXT_60_DAYS => [
                'start' => $now->copy()->startOfDay(),
                'end'   => $now->copy()->addDays(59)->endOfDay(),
            ],
            self::NEXT_90_DAYS => [
                'start' => $now->copy()->startOfDay(),
                'end'   => $now->copy()->addDays(89)->endOfDay(),
            ],
        };
    }

    public static function pastPresets(): array
    {
        return collect([
            self::TODAY, self::YESTERDAY, self::LAST_7_DAYS, self::LAST_WEEK,
            self::LAST_30_DAYS, self::LAST_MONTH, self::THIS_MONTH,
            self::LAST_QUARTER, self::THIS_YEAR, self::LAST_YEAR,
        ])->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }

    public static function futurePresets(): array
    {
        return collect([
            self::NEXT_7_DAYS, self::NEXT_30_DAYS, self::NEXT_60_DAYS, self::NEXT_90_DAYS,
        ])->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])->all();
    }
}
