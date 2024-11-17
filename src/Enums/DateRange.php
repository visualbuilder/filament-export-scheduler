<?php

namespace VisualBuilder\ExportScheduler\Enums;

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

    public function getLabel(): string
    {
        return match ($this) {
            self::TODAY => __('export-scheduler::date_ranges.today'),
            self::YESTERDAY => __('export-scheduler::date_ranges.yesterday'),
            self::LAST_7_DAYS => __('export-scheduler::date_ranges.last_7_days'),
            self::LAST_WEEK => __('export-scheduler::date_ranges.last_week'),
            self::LAST_30_DAYS => __('export-scheduler::date_ranges.last_30_days'),
            self::LAST_MONTH => __('export-scheduler::date_ranges.last_month'),
            self::THIS_MONTH => __('export-scheduler::date_ranges.this_month'),
            self::LAST_QUARTER => __('export-scheduler::date_ranges.last_quarter'),
            self::THIS_YEAR => __('export-scheduler::date_ranges.this_year'),
            self::LAST_YEAR => __('export-scheduler::date_ranges.last_year'),
            // Add more labels as needed
        };
    }

    // Optional: Method to get the start and end dates
    public function getDateRange(): array
    {
        $now = \Carbon\Carbon::now();

        return match ($this) {
            self::TODAY => [
                'start' => $now->copy()->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
            self::YESTERDAY => [
                'start' => $now->copy()->subDay()->startOfDay(),
                'end' => $now->copy()->subDay()->endOfDay(),
            ],
            self::LAST_7_DAYS => [
                'start' => $now->copy()->subDays(6)->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
            self::LAST_WEEK => [
                'start' => $now->copy()->subWeek()->startOfWeek(),
                'end' => $now->copy()->subWeek()->endOfWeek(),
            ],
            self::LAST_30_DAYS => [
                'start' => $now->copy()->subDays(29)->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
            self::LAST_MONTH => [
                'start' => $now->copy()->subMonth()->startOfMonth(),
                'end' => $now->copy()->subMonth()->endOfMonth(),
            ],
            self::THIS_MONTH => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth(),
            ],
            self::LAST_QUARTER => [
                'start' => $now->copy()->subQuarter()->firstOfQuarter(),
                'end' => $now->copy()->subQuarter()->lastOfQuarter(),
            ],
            self::THIS_YEAR => [
                'start' => $now->copy()->startOfYear(),
                'end' => $now->copy()->endOfYear(),
            ],
            self::LAST_YEAR => [
                'start' => $now->copy()->subYear()->startOfYear(),
                'end' => $now->copy()->subYear()->endOfYear(),
            ],

        };
    }
}
