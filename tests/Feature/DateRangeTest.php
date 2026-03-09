<?php

use Carbon\Carbon;
use Visualbuilder\ExportScheduler\Enums\DateRange;

beforeEach(function () {
    Carbon::setTestNow('2024-06-15 12:00:00'); // Saturday, mid-month, mid-year
});

it('calculates TODAY date range correctly', function () {
    $range = DateRange::TODAY->getDateRange();

    expect($range['start']->toDateTimeString())->toBe('2024-06-15 00:00:00');
    expect($range['end']->toDateTimeString())->toBe('2024-06-15 23:59:59');
});

it('calculates YESTERDAY date range correctly', function () {
    $range = DateRange::YESTERDAY->getDateRange();

    expect($range['start']->toDateTimeString())->toBe('2024-06-14 00:00:00');
    expect($range['end']->toDateTimeString())->toBe('2024-06-14 23:59:59');
});

it('calculates LAST_7_DAYS date range correctly', function () {
    $range = DateRange::LAST_7_DAYS->getDateRange();

    expect($range['start']->toDateTimeString())->toBe('2024-06-09 00:00:00');
    expect($range['end']->toDateTimeString())->toBe('2024-06-15 23:59:59');
});

it('calculates LAST_WEEK date range correctly', function () {
    $range = DateRange::LAST_WEEK->getDateRange();

    // Previous week: Monday June 3 to Sunday June 9
    expect($range['start']->toDateTimeString())->toBe('2024-06-03 00:00:00');
    expect($range['end']->toDateTimeString())->toBe('2024-06-09 23:59:59');
});

it('calculates LAST_30_DAYS date range correctly', function () {
    $range = DateRange::LAST_30_DAYS->getDateRange();

    expect($range['start']->toDateTimeString())->toBe('2024-05-17 00:00:00');
    expect($range['end']->toDateTimeString())->toBe('2024-06-15 23:59:59');
});

it('calculates LAST_MONTH date range correctly', function () {
    $range = DateRange::LAST_MONTH->getDateRange();

    expect($range['start']->toDateTimeString())->toBe('2024-05-01 00:00:00');
    expect($range['end']->toDateTimeString())->toBe('2024-05-31 23:59:59');
});

it('calculates THIS_MONTH date range correctly', function () {
    $range = DateRange::THIS_MONTH->getDateRange();

    expect($range['start']->toDateTimeString())->toBe('2024-06-01 00:00:00');
    expect($range['end']->toDateTimeString())->toBe('2024-06-30 23:59:59');
});

it('calculates LAST_QUARTER date range correctly', function () {
    $range = DateRange::LAST_QUARTER->getDateRange();

    // From June 15, 2024, last quarter is Q1 2024: January 1 - March 31
    expect($range['start']->format('Y-m-d'))->toBe('2024-01-01');
    expect($range['end']->format('Y-m-d'))->toBe('2024-03-31');
});

it('calculates THIS_YEAR date range correctly', function () {
    $range = DateRange::THIS_YEAR->getDateRange();

    expect($range['start']->toDateTimeString())->toBe('2024-01-01 00:00:00');
    expect($range['end']->toDateTimeString())->toBe('2024-12-31 23:59:59');
});

it('calculates LAST_YEAR date range correctly', function () {
    $range = DateRange::LAST_YEAR->getDateRange();

    expect($range['start']->toDateTimeString())->toBe('2023-01-01 00:00:00');
    expect($range['end']->toDateTimeString())->toBe('2023-12-31 23:59:59');
});

it('handles date ranges at year boundary correctly', function () {
    Carbon::setTestNow('2024-01-05 12:00:00');

    $lastWeek = DateRange::LAST_WEEK->getDateRange();
    expect($lastWeek['start']->year)->toBe(2023);
    expect($lastWeek['end']->year)->toBe(2023);

    $last30Days = DateRange::LAST_30_DAYS->getDateRange();
    expect($last30Days['start']->year)->toBe(2023);
    expect($last30Days['end']->year)->toBe(2024);
});

it('handles date ranges in leap year February correctly', function () {
    Carbon::setTestNow('2024-02-15 12:00:00');

    $thisMonth = DateRange::THIS_MONTH->getDateRange();
    expect($thisMonth['end']->day)->toBe(29); // 2024 is a leap year
});

it('handles date ranges in non-leap year February correctly', function () {
    Carbon::setTestNow('2023-02-15 12:00:00');

    $thisMonth = DateRange::THIS_MONTH->getDateRange();
    expect($thisMonth['end']->day)->toBe(28); // 2023 is not a leap year
});

it('calculates NEXT_7_DAYS date range correctly', function () {
    $range = DateRange::NEXT_7_DAYS->getDateRange();

    expect($range['start']->toDateTimeString())->toBe('2024-06-15 00:00:00');
    expect($range['end']->toDateTimeString())->toBe('2024-06-21 23:59:59');
});

it('calculates NEXT_30_DAYS date range correctly', function () {
    $range = DateRange::NEXT_30_DAYS->getDateRange();

    expect($range['start']->toDateTimeString())->toBe('2024-06-15 00:00:00');
    expect($range['end']->toDateTimeString())->toBe('2024-07-14 23:59:59');
});

it('calculates NEXT_60_DAYS date range correctly', function () {
    $range = DateRange::NEXT_60_DAYS->getDateRange();

    expect($range['start']->toDateTimeString())->toBe('2024-06-15 00:00:00');
    expect($range['end']->toDateTimeString())->toBe('2024-08-13 23:59:59');
});

it('calculates NEXT_90_DAYS date range correctly', function () {
    $range = DateRange::NEXT_90_DAYS->getDateRange();

    expect($range['start']->toDateTimeString())->toBe('2024-06-15 00:00:00');
    expect($range['end']->toDateTimeString())->toBe('2024-09-12 23:59:59');
});

it('returns proper labels for all date ranges', function () {
    foreach (DateRange::cases() as $dateRange) {
        expect($dateRange->getLabel())
            ->toBeString()
            ->toEqual(__('export-scheduler::date_ranges.'.$dateRange->value));
    }
});
