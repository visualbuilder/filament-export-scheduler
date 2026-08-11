<?php

use Carbon\Carbon;
use Filament\Actions\Exports\Enums\ExportFormat;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Visualbuilder\ExportScheduler\Enums\DayOfWeek;
use Visualbuilder\ExportScheduler\Enums\Month;
use Visualbuilder\ExportScheduler\Enums\ScheduleFrequency;
use Visualbuilder\ExportScheduler\Filament\Exporters\UserExporter;
use Visualbuilder\ExportScheduler\Models\CustomReport;
use Visualbuilder\ExportScheduler\Models\ScheduledReport;

beforeEach(function () {
    Notification::fake();
    Log::spy();
});

it('successfully runs export command for due schedules', function () {
        $scheduleReport = CustomReport::create([
        'name' => 'Test Schedule',
        'exporter' => UserExporter::class,
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'formats' => [ExportFormat::Csv],
    ]);

    $schedule = ScheduledReport::create([
        'custom_report_id' => $scheduleReport->id,
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => now()->toTimeString(),
        'next_run_at' => now()->subMinute(),
        'enabled' => true,
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
        $scheduleReport = CustomReport::create([
        'name' => 'Disabled Schedule',
        'exporter' => UserExporter::class,
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'formats' => [ExportFormat::Csv],
    ]);

    $schedule = ScheduledReport::create([
        'custom_report_id' => $scheduleReport->id,
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => now()->toTimeString(),
        'next_run_at' => now()->subMinute(),
        'enabled' => false,
    ]);

    expect($schedule->refresh()->last_run_at)->toBeNull();

    $this->artisan('export:run');

    expect($schedule->refresh()->last_run_at)->toBeNull();
});

it('skips schedules not yet due', function () {
        $scheduleReport = CustomReport::create([
        'name' => 'Future Schedule',
        'exporter' => UserExporter::class,
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'formats' => [ExportFormat::Csv],
    ]);

    $schedule = ScheduledReport::create([
        'custom_report_id' => $scheduleReport->id,
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => now()->toTimeString(),
        'next_run_at' => now()->addHour(),
        'enabled' => true,
    ]);

    expect($schedule->refresh()->last_run_at)->toBeNull();

    $this->artisan('export:run');

    expect($schedule->refresh()->last_run_at)->toBeNull();
});

it('processes multiple due schedules', function () {
    $report = [
        'exporter' => UserExporter::class,
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'formats' => [ExportFormat::Csv]
    ];

    $schedule = [
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => now()->toTimeString(),
        'next_run_at' => now()->subMinute(),
        'enabled' => true,
    ];

    $report1 = CustomReport::create($report + ['name' => 'Schedule 1']);
    $schedule1 = ScheduledReport::create($schedule + ['custom_report_id' => $report1->id]);

    $report2 = CustomReport::create($report + ['name' => 'Schedule 2']);
    $schedule2 = ScheduledReport::create($schedule + ['custom_report_id' => $report2->id]);

    expect($schedule1->refresh()->last_run_at)->toBeNull();
    expect($schedule2->refresh()->last_run_at)->toBeNull();

    $this->artisan('export:run');

    expect($schedule1->refresh()->last_run_at)->not->toBeNull();
    expect($schedule2->refresh()->last_run_at)->not->toBeNull();
});

it('calculates next run time correctly after execution', function () {
    Carbon::setTestNow('2024-06-15 10:00:00');

    $scheduleReport = CustomReport::create([
        'name' => 'Daily Schedule',
        'exporter' => UserExporter::class,
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'formats' => [ExportFormat::Csv],
    ]);

    $schedule = ScheduledReport::create([
        'custom_report_id' => $scheduleReport->id,
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => '10:00:00',
        'next_run_at' => Carbon::parse('2024-06-15 10:00:00'),
        'enabled' => true,
    ]);

    $this->artisan('export:run');

    expect($schedule->refresh()->next_run_at->toDateString())->toBe('2024-06-16');
});

it('runs daily schedule with null next_run_at when calculated run time is due', function () {
    Carbon::setTestNow('2024-06-10 10:00:00');

    $scheduleReport = CustomReport::create([
        'name' => 'Schedule with null next_run_at',
        'exporter' => UserExporter::class,
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'formats' => [ExportFormat::Csv],
    ]);

    $schedule = ScheduledReport::create([
        'custom_report_id' => $scheduleReport->id,
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => '09:00:00', // Earlier than current time
        'enabled' => true,
    ]);

    $schedule->updateQuietly(['next_run_at' => null]);

    expect($schedule->refresh())
        ->next_run_at->toBeNull()
        ->last_run_at->toBeNull();

    $this->artisan('export:run')
        ->assertExitCode(0);

    expect($schedule->refresh())
        ->last_run_at->not->toBeNull()
        ->last_run_at->toDateString()->toBe('2024-06-10')
        ->last_successful_run_at->not->toBeNull()
        ->last_successful_run_at->toDateString()->toBe('2024-06-10')
        ->next_run_at->not->toBeNull()
        ->next_run_at->toDateString()->toBe('2024-06-11');
});

it('skips daily schedule with null next_run_at when calculated run time is not due', function () {
    Carbon::setTestNow('2024-06-15 10:00:00');

    $scheduleReport = CustomReport::create([
        'name' => 'Schedule with null next_run_at',
        'exporter' => UserExporter::class,
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'formats' => [ExportFormat::Csv],
    ]);

    $schedule = ScheduledReport::create([
        'custom_report_id' => $scheduleReport->id,
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => '11:00:00', // Later than current time
        'enabled' => true,
    ]);

    $schedule->updateQuietly(['next_run_at' => null]);

    expect($schedule->refresh())
        ->next_run_at->toBeNull()
        ->last_run_at->toBeNull();

    $this->artisan('export:run')
        ->assertExitCode(0);

    expect($schedule->refresh())
        ->last_run_at->toBeNull()
        ->last_successful_run_at->toBeNull()
        ->next_run_at->toBeNull();
});

it('runs weekly schedule with null next_run_at on correct day and time', function () {
    // June 15, 2024 is a Saturday
    Carbon::setTestNow('2024-06-15 10:00:00');

    $scheduleReport = CustomReport::create([
        'name' => 'Weekly Schedule',
        'exporter' => UserExporter::class,
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'formats' => [ExportFormat::Csv],
    ]);

    $schedule = ScheduledReport::create([
        'custom_report_id' => $scheduleReport->id,
        'schedule_frequency' => ScheduleFrequency::WEEKLY,
        'schedule_time' => '09:00:00',
        'schedule_day_of_week' => DayOfWeek::SATURDAY, // Saturday
        'enabled' => true,
    ]);

    $schedule->updateQuietly(['next_run_at' => null]);

    expect($schedule->refresh())
        ->next_run_at->toBeNull()
        ->last_run_at->toBeNull();

    $this->artisan('export:run')
        ->assertExitCode(0);

    expect($schedule->refresh())
        ->last_run_at->not->toBeNull()
        ->last_run_at->toDateString()->toBe('2024-06-15')
        ->last_successful_run_at->not->toBeNull()
        ->last_successful_run_at->toDateString()->toBe('2024-06-15')
        ->next_run_at->not->toBeNull()
        ->next_run_at->toDateString()->toBe('2024-06-22');
});

it('skips weekly schedule with null next_run_at on wrong day', function () {
    // June 15, 2024 is a Saturday
    Carbon::setTestNow('2024-06-15 10:00:00');

    $scheduleReport = CustomReport::create([
        'name' => 'Weekly Schedule',
        'exporter' => UserExporter::class,
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'formats' => [ExportFormat::Csv],
    ]);

    $schedule = ScheduledReport::create([
        'custom_report_id' => $scheduleReport->id,
        'schedule_frequency' => ScheduleFrequency::WEEKLY,
        'schedule_time' => '09:00:00',
        'schedule_day_of_week' => DayOfWeek::MONDAY, // Monday
        'enabled' => true,
    ]);

    $schedule->updateQuietly(['next_run_at' => null]);

    $this->artisan('export:run')
        ->assertExitCode(0);

    expect($schedule->refresh()->last_run_at)->toBeNull();
});

it('runs monthly schedule with null next_run_at on correct day and time', function () {
    // June 15, 2024
    Carbon::setTestNow('2024-06-15 10:00:00');

    $scheduleReport = CustomReport::create([
        'name' => 'Monthly Schedule',
        'exporter' => UserExporter::class,
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'formats' => [ExportFormat::Csv],
    ]);

    $schedule = ScheduledReport::create([
        'custom_report_id' => $scheduleReport->id,
        'schedule_frequency' => ScheduleFrequency::MONTHLY,
        'schedule_time' => '09:00:00',
        'schedule_day_of_month' => 15,
        'enabled' => true,
    ]);

    $schedule->updateQuietly(['next_run_at' => null]);

    expect($schedule->refresh())
        ->next_run_at->toBeNull()
        ->last_run_at->toBeNull();

    $this->artisan('export:run')
        ->assertExitCode(0);

    expect($schedule->refresh())
        ->last_run_at->not->toBeNull()
        ->last_run_at->toDateString()->toBe('2024-06-15')
        ->last_successful_run_at->not->toBeNull()
        ->last_successful_run_at->toDateString()->toBe('2024-06-15')
        ->next_run_at->not->toBeNull()
        ->next_run_at->toDateString()->toBe('2024-07-15');
});

it('skips monthly schedule with null next_run_at on wrong day', function () {
    // June 15, 2024
    Carbon::setTestNow('2024-06-15 10:00:00');

    $scheduleReport = CustomReport::create([
        'name' => 'Monthly Schedule',
        'exporter' => UserExporter::class,
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'formats' => [ExportFormat::Csv],
    ]);

    $schedule = ScheduledReport::create([
        'custom_report_id' => $scheduleReport->id,
        'schedule_frequency' => ScheduleFrequency::MONTHLY,
        'schedule_time' => '09:00:00',
        'schedule_day_of_month' => 20,
        'enabled' => true,
    ]);

    $schedule->updateQuietly(['next_run_at' => null]);

    $this->artisan('export:run')
        ->assertExitCode(0);

    expect($schedule->refresh()->last_run_at)->toBeNull();
});

it('runs quarterly schedule with null next_run_at on correct month, day and time', function () {
    // June 15, 2024
    Carbon::setTestNow('2024-09-15 10:00:00');

    $scheduleReport = CustomReport::create([
        'name' => 'Quarterly Schedule',
        'exporter' => UserExporter::class,
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'formats' => [ExportFormat::Csv],
    ]);

    $schedule = ScheduledReport::create([
        'custom_report_id' => $scheduleReport->id,
        'schedule_frequency' => ScheduleFrequency::QUARTERLY,
        'schedule_time' => '09:00:00',
        'schedule_day_of_month' => 15,
        'schedule_month' => Month::MARCH,
        'enabled' => true,
    ]);

    $schedule->updateQuietly(['next_run_at' => null]);

    $this->artisan('export:run')
        ->assertExitCode(0);

    expect($schedule->refresh())
        ->last_run_at->not->toBeNull()
        ->last_run_at->toDateString()->toBe('2024-09-15')
        ->last_successful_run_at->not->toBeNull()
        ->last_successful_run_at->toDateString()->toBe('2024-09-15')
        ->next_run_at->not->toBeNull()
        ->next_run_at->toDateString()->toBe('2024-12-15');
});

it('skips quarterly schedule with null next_run_at on wrong month or day', function () {
    // wrong date not in quarterly cycle August 15, 2024
    Carbon::setTestNow('2024-08-15 10:00:00');

    $scheduleReport = CustomReport::create([
        'name' => 'Quarterly Schedule',
        'exporter' => UserExporter::class,
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'formats' => [ExportFormat::Csv],
    ]);

    $schedule = ScheduledReport::create([
        'custom_report_id' => $scheduleReport->id,
        'schedule_frequency' => ScheduleFrequency::QUARTERLY,
        'schedule_time' => '09:00:00',
        'schedule_day_of_month' => 15,
        'schedule_month' => Month::MARCH,  // cycle: March, June, September, December
        'enabled' => true,
    ]);

    $schedule->updateQuietly(['next_run_at' => null]);

    $this->artisan('export:run')
        ->assertExitCode(0);

    expect($schedule->refresh()->last_run_at)->toBeNull();
});

it('runs half-yearly schedule with null next_run_at on correct month, day and time', function () {
    // June 15, 2024
    Carbon::setTestNow('2024-06-15 10:00:00');

    $scheduleReport = CustomReport::create([
        'name' => 'Half-Yearly Schedule',
        'exporter' => UserExporter::class,
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'formats' => [ExportFormat::Csv],
    ]);

    $schedule = ScheduledReport::create([
        'custom_report_id' => $scheduleReport->id,
        'schedule_frequency' => ScheduleFrequency::HALF_YEARLY,
        'schedule_time' => '09:00:00',
        'schedule_day_of_month' => 15,
        'schedule_month' => Month::DECEMBER,
        'enabled' => true,
    ]);

    $schedule->updateQuietly(['next_run_at' => null]);

    $this->artisan('export:run')
        ->assertExitCode(0);

    expect($schedule->refresh())
        ->last_run_at->not->toBeNull()
        ->last_run_at->toDateString()->toBe('2024-06-15')
        ->last_successful_run_at->not->toBeNull()
        ->last_successful_run_at->toDateString()->toBe('2024-06-15')
        ->next_run_at->not->toBeNull()
        ->next_run_at->toDateString()->toBe('2024-12-15');
});

it('skips half-yearly schedule with null next_run_at on wrong month or day', function () {
    // wrong date not in half-yearly cycle June 15, 2024
    Carbon::setTestNow('2024-06-15 10:00:00');

    $scheduleReport = CustomReport::create([
        'name' => 'Half-Yearly Schedule',
        'exporter' => UserExporter::class,
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'formats' => [ExportFormat::Csv],
    ]);

    $schedule = ScheduledReport::create([
        'custom_report_id' => $scheduleReport->id,
        'schedule_frequency' => ScheduleFrequency::HALF_YEARLY,
        'schedule_time' => '09:00:00',
        'schedule_day_of_month' => 15,
        'schedule_month' => Month::JANUARY,    // cycle: January & July
        'enabled' => true,
    ]);

    $schedule->updateQuietly(['next_run_at' => null]);

    $this->artisan('export:run')
        ->assertExitCode(0);

    expect($schedule->refresh()->last_run_at)->toBeNull();
});

it('runs yearly schedule with null next_run_at on correct month, day and time', function () {
    // June 15, 2024
    Carbon::setTestNow('2024-06-15 10:00:00');

    $scheduleReport = CustomReport::create([
        'name' => 'Yearly Schedule',
        'exporter' => UserExporter::class,
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'formats' => [ExportFormat::Csv],
    ]);

    $schedule = ScheduledReport::create([
        'custom_report_id' => $scheduleReport->id,
        'schedule_frequency' => ScheduleFrequency::YEARLY,
        'schedule_time' => '09:00:00',
        'schedule_day_of_month' => 15,
        'schedule_month' => Month::JUNE,
        'enabled' => true,
    ]);

    $schedule->updateQuietly(['next_run_at' => null]);

    $this->artisan('export:run')
        ->assertExitCode(0);

    expect($schedule->refresh())
        ->last_run_at->not->toBeNull()
        ->last_run_at->toDateString()->toBe('2024-06-15')
        ->last_successful_run_at->not->toBeNull()
        ->last_successful_run_at->toDateString()->toBe('2024-06-15')
        ->next_run_at->not->toBeNull()
        ->next_run_at->toDateString()->toBe('2025-06-15');
});

it('skips yearly schedule with null next_run_at on wrong month or day', function () {
    // June 15, 2024
    Carbon::setTestNow('2024-06-15 10:00:00');

    $scheduleReport = CustomReport::create([
        'name' => 'Yearly Schedule',
        'exporter' => UserExporter::class,
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'formats' => [ExportFormat::Csv],
    ]);

    $schedule = ScheduledReport::create([
        'custom_report_id' => $scheduleReport->id,
        'schedule_frequency' => ScheduleFrequency::YEARLY,
        'schedule_time' => '09:00:00',
        'schedule_day_of_month' => 15,
        'schedule_month' => Month::DECEMBER, // Wrong month
        'enabled' => true,
    ]);

    $schedule->updateQuietly(['next_run_at' => null]);

    $this->artisan('export:run')
        ->assertExitCode(0);

    expect($schedule->refresh()->last_run_at)->toBeNull();
});

it('runs quarterly schedule in second quarter when configured for first quarter', function () {
    // June 15, 2024 (Q2) - Schedule configured for March 15 (Q1)
    Carbon::setTestNow('2024-06-15 10:00:00');

    $scheduleReport = CustomReport::create([
        'name' => 'Quarterly Schedule',
        'exporter' => UserExporter::class,
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'formats' => [ExportFormat::Csv],
    ]);

    $schedule = ScheduledReport::create([
        'custom_report_id' => $scheduleReport->id,
        'schedule_frequency' => ScheduleFrequency::QUARTERLY,
        'schedule_time' => '09:00:00',
        'schedule_day_of_month' => 15,
        'schedule_month' => Month::MARCH, // Configured for March
        'enabled' => true,
    ]);

    $schedule->updateQuietly(['next_run_at' => null]);

    $this->artisan('export:run')
        ->assertExitCode(0);

    // June is 3 months after March (March + 3 = June), so it should run
    expect($schedule->refresh())
        ->last_run_at->not->toBeNull()
        ->last_run_at->toDateString()->toBe('2024-06-15')
        ->last_successful_run_at->not->toBeNull()
        ->last_successful_run_at->toDateString()->toBe('2024-06-15')
        ->next_run_at->not->toBeNull()
        ->next_run_at->toDateString()->toBe('2024-09-15');  // Next run in September (June + 3)
});

it('runs half-yearly schedule in second half when configured for first half', function () {
    // December 15, 2024 (H2) - Schedule configured for June 15 (H1)
    Carbon::setTestNow('2024-12-15 10:00:00');

    $scheduleReport = CustomReport::create([
        'name' => 'Half-Yearly Schedule',
        'exporter' => UserExporter::class,
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'formats' => [ExportFormat::Csv],
    ]);

    $schedule = ScheduledReport::create([
        'custom_report_id' => $scheduleReport->id,
        'schedule_frequency' => ScheduleFrequency::HALF_YEARLY,
        'schedule_time' => '09:00:00',
        'schedule_day_of_month' => 15,
        'schedule_month' => Month::JUNE, // Configured for June
        'enabled' => true,
    ]);

    $schedule->updateQuietly(['next_run_at' => null]);

    $this->artisan('export:run')
        ->assertExitCode(0);

    // December is 6 months after June (June + 6 = December), so it should run
    expect($schedule->refresh())
        ->last_run_at->not->toBeNull()
        ->last_run_at->toDateString()->toBe('2024-12-15')
        ->last_successful_run_at->not->toBeNull()
        ->last_successful_run_at->toDateString()->toBe('2024-12-15')
        ->next_run_at->not->toBeNull()
        ->next_run_at->toDateString()->toBe('2025-06-15'); // Next run in June (December + 6)
});
