<?php

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Visualbuilder\ExportScheduler\Enums\DateRange;
use Visualbuilder\ExportScheduler\Enums\DayOfWeek;
use Visualbuilder\ExportScheduler\Enums\ScheduleFrequency;
use Visualbuilder\ExportScheduler\Filament\Exporters\UserExporter;
use Visualbuilder\ExportScheduler\Models\ExportSchedule;

beforeEach(function () {
    $this->assertDatabaseCount('export_schedules', 0);
});

it('filters enabled schedules with scopeEnabled', function () {
    ExportSchedule::create([
        'name' => 'Enabled Schedule',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => now()->toTimeString(),
        'enabled' => true,
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);

    ExportSchedule::create([
        'name' => 'Disabled Schedule',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => now()->toTimeString(),
        'enabled' => false,
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);

    expect(ExportSchedule::enabled())
        ->count()->toBe(1)
        ->first()->name->toBe('Enabled Schedule');
});

it('filters schedules due to run with scopeNextRunDue', function () {
    $data = [
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => now()->toTimeString(),
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ];

    // future schedule
    ExportSchedule::create($data + [
        'name' => 'Future Schedule',
        'next_run_at' => now()->addHour(),
    ]);

    // past schedule
    $pastSchedule = ExportSchedule::create($data + [
        'name' => 'Past Schedule',
        'next_run_at' => now()->subHour(),
    ]);

    expect(ExportSchedule::nextRunDue()->get())
        ->count()->toBe(1)
        ->first()->name->toBe($pastSchedule->name);
});

it('returns correct date range from date_range attribute', function () {
    Carbon::setTestNow('2024-01-15 12:00:00');

    $schedule = ExportSchedule::create([
        'name' => 'Test Schedule',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => now()->toTimeString(),
        'date_range' => DateRange::TODAY,
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);

    expect($schedule->starts_at)
        ->toBeInstanceOf(Carbon::class)
        ->toDateString()->toBe('2024-01-15');

    expect($schedule->ends_at)
        ->toBeInstanceOf(Carbon::class)
        ->toDateString()->toBe('2024-01-15');
});

it('returns formatted date strings', function () {
    Carbon::setTestNow('2024-01-15 12:00:00');

    $schedule = ExportSchedule::create([
        'name' => 'Test Schedule',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => now()->toTimeString(),
        'date_range' => DateRange::TODAY,
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);

    expect($schedule->starts_at_formatted)->toContain('Monday 15th January 2024');
    expect($schedule->ends_at_formatted)->toContain('Monday 15th January 2024');
});

it('returns frequency label', function () {
    $schedule = ExportSchedule::create([
        'name' => 'Test Schedule',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::WEEKLY,
        'schedule_time' => now()->toTimeString(),
        'schedule_day_of_week' => DayOfWeek::MONDAY,
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);

    expect($schedule->frequency)->toBe(ScheduleFrequency::WEEKLY->getLabel());
});

it('returns date range label', function () {
    $schedule = ExportSchedule::create([
        'name' => 'Test Schedule',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => now()->toTimeString(),
        'date_range' => DateRange::LAST_7_DAYS,
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);

    expect($schedule->date_range_label)->toBe(DateRange::LAST_7_DAYS->getLabel());
});

it('returns default columns for exporter', function () {
    expect(ExportSchedule::getDefaultColumnsForExporter(UserExporter::class))
        ->toBeInstanceOf(Collection::class)
        ->count()->toBeGreaterThan(0)
        ->first()->toHaveKeys(['name', 'label']);
});

it('returns empty collection for invalid exporter class', function () {
    expect(ExportSchedule::getDefaultColumnsForExporter('InvalidClass'))
        ->toBeInstanceOf(Collection::class)
        ->count()->toBe(0);
});

it('calculates next_run_at automatically on creation', function () {
    $schedule = ExportSchedule::create([
        'name' => 'Test Schedule',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => now()->toTimeString(),
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);

    expect($schedule->next_run_at)
        ->not->toBeNull()
        ->toBeInstanceOf(Carbon::class);
});

it('recalculates next_run_at when schedule fields change', function () {
    $schedule = ExportSchedule::create([
        'name' => 'Test Schedule',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => '10:00:00',
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);

    $originalNextRun = $schedule->next_run_at;

    // Change schedule time
    $schedule->update(['schedule_time' => '14:00:00']);

    expect($schedule->next_run_at)->not->toEqual($originalNextRun);
});

it('does not recalculate next_run_at when non-schedule fields change', function () {
    $schedule = ExportSchedule::create([
        'name' => 'Test Schedule',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => '10:00:00',
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);

    $originalNextRun = $schedule->next_run_at;

    // Change non-schedule field
    $schedule->update(['name' => 'Updated Name']);

    expect($schedule->next_run_at->timestamp)->toBe($originalNextRun->timestamp);
});

it('counts cc recipients', function () {
    $schedule = ExportSchedule::create([
        'name' => 'Test Schedule',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => now()->toTimeString(),
        'cc' => ['user1@example.com', 'user2@example.com', 'user3@example.com'],
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);

    expect($schedule->cc_count)->toBe(3);
});

it('identifies current user as owner', function () {
    $schedule = ExportSchedule::create([
        'name' => 'Test Schedule',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => now()->toTimeString(),
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);

    expect($schedule->isCurrentUserOwner())->toBeTrue();
});
