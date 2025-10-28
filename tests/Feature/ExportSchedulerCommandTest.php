<?php

use Carbon\Carbon;
use Filament\Actions\Exports\Enums\ExportFormat;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Visualbuilder\ExportScheduler\Enums\ScheduleFrequency;
use Visualbuilder\ExportScheduler\Filament\Exporters\UserExporter;
use Visualbuilder\ExportScheduler\Models\ExportSchedule;

beforeEach(function () {
    Notification::fake();
    Log::spy();
});

it('successfully runs export command for due schedules', function () {
    $schedule = ExportSchedule::create([
        'name' => 'Test Schedule',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => now()->toTimeString(),
        'next_run_at' => now()->subMinute(),
        'enabled' => true,
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'formats' => [ExportFormat::Csv],
    ]);

    expect($schedule->refresh())
        ->last_run_at->toBeNull()
        ->last_successful_run_at->toBeNull();

    $this->artisan('export:run')
        ->assertExitCode(0);

    expect($schedule->refresh())
        ->last_run_at->not->toBeNull()
        ->last_successful_run_at->not->toBeNull();
});

it('skips disabled schedules', function () {
    $schedule = ExportSchedule::create([
        'name' => 'Disabled Schedule',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => now()->toTimeString(),
        'next_run_at' => now()->subMinute(),
        'enabled' => false,
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'formats' => [ExportFormat::Csv],
    ]);

    expect($schedule->refresh()->last_run_at)->toBeNull();

    $this->artisan('export:run');

    expect($schedule->refresh()->last_run_at)->toBeNull();
});

it('skips schedules not yet due', function () {
    $schedule = ExportSchedule::create([
        'name' => 'Future Schedule',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => now()->toTimeString(),
        'next_run_at' => now()->addHour(),
        'enabled' => true,
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'formats' => [ExportFormat::Csv],
    ]);

    expect($schedule->refresh()->last_run_at)->toBeNull();

    $this->artisan('export:run');

    expect($schedule->refresh()->last_run_at)->toBeNull();
});

it('processes multiple due schedules', function () {
    $schedule = [
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => now()->toTimeString(),
        'next_run_at' => now()->subMinute(),
        'enabled' => true,
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'formats' => [ExportFormat::Csv]
    ];

    $schedule1 = ExportSchedule::create($schedule + ['name' => 'Schedule 1']);
    $schedule2 = ExportSchedule::create($schedule + ['name' => 'Schedule 2']);

    expect($schedule1->refresh()->last_run_at)->toBeNull();
    expect($schedule2->refresh()->last_run_at)->toBeNull();

    $this->artisan('export:run');

    expect($schedule1->refresh()->last_run_at)->not->toBeNull();
    expect($schedule2->refresh()->last_run_at)->not->toBeNull();
});

it('calculates next run time correctly after execution', function () {
    Carbon::setTestNow('2024-06-15 10:00:00');

    $schedule = ExportSchedule::create([
        'name' => 'Daily Schedule',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => '10:00:00',
        'next_run_at' => Carbon::parse('2024-06-15 10:00:00'),
        'enabled' => true,
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'formats' => [ExportFormat::Csv],
    ]);

    $this->artisan('export:run');

    expect($schedule->refresh()->next_run_at->toDateString())->toBe('2024-06-16');
});
