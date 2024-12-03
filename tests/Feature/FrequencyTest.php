<?php

use Carbon\Carbon;
use Filament\Actions\Exports\Enums\ExportFormat;
use Illuminate\Support\Facades\Notification;
use VisualBuilder\ExportScheduler\Enums\DayOfWeek;
use VisualBuilder\ExportScheduler\Enums\Month;
use VisualBuilder\ExportScheduler\Enums\ScheduleFrequency;
use VisualBuilder\ExportScheduler\Filament\Exporters\UserExporter;
use VisualBuilder\ExportScheduler\Models\ExportSchedule;
use VisualBuilder\ExportScheduler\Notifications\ScheduledExportCompleteNotification;

beforeEach(function () {
    Notification::fake();
    $this->assertDatabaseEmpty('exports');
    $this->assertDatabaseEmpty('export_schedules');
    $this->assertDatabaseCount('users', 1);
});

it('sends a daily export email every day for 3 days', function () {
    $now = now();

    $dailySchedule = ExportSchedule::create([
        'name' => 'User Export Daily',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'formats' => [ExportFormat::Csv],
        'schedule_time' => $now->toTimeString(),
        'next_run_at' => $now,
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
            ['name' => 'created_at', 'label' => 'Created At', 'formatter' => 'datetime'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);
    $this->assertDatabaseCount('export_schedules', 1);
    $this->assertDatabaseHas($dailySchedule, ['schedule_frequency' => ScheduleFrequency::DAILY]);

    $testTime = $now;
    for ($i = 0; $i < 24 * 3; $i++) {   // run every hour every day for 3 days
        Carbon::setTestNow($testTime);
        $this->artisan('export:run');
        $testTime->addHour();
    }
    Notification::assertCount(3);
    Notification::assertSentTo($dailySchedule->owner, ScheduledExportCompleteNotification::class);
});

it('sends a weekly export email every week (Wednesday) for 3 weeks', function () {
    $wednesday = now()->weekday(Carbon::WEDNESDAY);
    Carbon::setTestNow($wednesday);

    $weeklySchedule = ExportSchedule::create([
        'name' => 'User Export Weekly (Wednesday)',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::WEEKLY,
        'formats' => [ExportFormat::Csv],
        'schedule_time' => $wednesday->toTimeString(),
        'schedule_day_of_week' => DayOfWeek::WEDNESDAY,
        'next_run_at' => $wednesday,
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
            ['name' => 'created_at', 'label' => 'Created At', 'formatter' => 'datetime'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);
    $this->assertDatabaseCount('export_schedules', 1);
    $this->assertDatabaseHas($weeklySchedule, ['schedule_frequency' => ScheduleFrequency::WEEKLY]);

    $testTime = $wednesday;
    for ($i = 0; $i < 24 * 7 * 3; $i += 8) {   // run every 8 hours every day for 3 weeks
        Carbon::setTestNow($testTime);
        $this->artisan('export:run');
        $testTime->addHours(8);
    }
    Notification::assertCount(3);
    Notification::assertSentTo($weeklySchedule->owner, ScheduledExportCompleteNotification::class);
});

it('sends a monthly export email every month (15th)', function () {
    $firstDayOfTheYear = now()->firstOfYear();
    $dayOfTheMonth = 15;
    $numOfDays = $firstDayOfTheYear->diffInDays($firstDayOfTheYear->copy()->endOfYear());

    $monthlySchedule = ExportSchedule::create([
        'name' => 'User Export Monthly (15th)',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::MONTHLY,
        'formats' => [ExportFormat::Csv],
        'schedule_time' => $firstDayOfTheYear->toTimeString(),
        'schedule_day_of_month' => $dayOfTheMonth,
        'next_run_at' => $firstDayOfTheYear->copy()->setDay($dayOfTheMonth),
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
            ['name' => 'created_at', 'label' => 'Created At', 'formatter' => 'datetime'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);
    $this->assertDatabaseCount('export_schedules', 1);
    $this->assertDatabaseHas($monthlySchedule, ['schedule_frequency' => ScheduleFrequency::MONTHLY]);

    $testTime = $firstDayOfTheYear;
    for ($i = 0; $i < $numOfDays; $i++) {   // run every once every day for a year
        Carbon::setTestNow($testTime);
        $this->artisan('export:run');
        $testTime->addDay();
    }
    Notification::assertCount(12);
    Notification::assertSentTo($monthlySchedule->owner, ScheduledExportCompleteNotification::class);
});

it('sends a monthly export email every last day of the month', function () {
    $firstDayOfTheYear = Carbon::createFromDate(2024)->firstOfYear();
    $dayOfTheMonth = -1;    // last day of the month
    $numOfDays = $firstDayOfTheYear->diffInDays($firstDayOfTheYear->copy()->addYear());
    $nextRun = $firstDayOfTheYear->copy()->endOfMonth()->setTime(0, 0);
    $monthlySchedule = ExportSchedule::create([
        'name' => 'User Export Monthly (-1)',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::MONTHLY,
        'formats' => [ExportFormat::Csv],
        'schedule_time' => $firstDayOfTheYear->toTimeString(),
        'schedule_day_of_month' => $dayOfTheMonth,
        'next_run_at' => $nextRun,
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
            ['name' => 'created_at', 'label' => 'Created At', 'formatter' => 'datetime'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);
    $this->assertDatabaseCount('export_schedules', 1);
    $this->assertDatabaseHas($monthlySchedule, ['schedule_frequency' => ScheduleFrequency::MONTHLY]);

    $testTime = $firstDayOfTheYear;
    for ($i = 0; $i < $numOfDays; $i++) {   // run every once every day for a year
        Carbon::setTestNow($testTime);
        fwrite(STDOUT, "Running test on day $i. ".$testTime->format("Y-m-d H:i:s")."\n");
        $this->artisan('export:run');
        $testTime->addDay();
    }
    Notification::assertCount(12);
    Notification::assertSentTo($monthlySchedule->owner, ScheduledExportCompleteNotification::class);
});

it('sends a monthly export email every month (29th; 28th for non-leap year)', function () {
    $nonLeapYear = Carbon::createFromDate(2023)->firstOfYear();
    $dayOfTheMonth = 28;
    $numOfDays = $nonLeapYear->diffInDays($nonLeapYear->copy()->endOfYear());

    $monthlySchedule = ExportSchedule::create([
        'name' => 'User Export Monthly (15th)',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::MONTHLY,
        'formats' => [ExportFormat::Csv],
        'schedule_time' => $nonLeapYear->toTimeString(),
        'schedule_day_of_month' => $dayOfTheMonth,
        'next_run_at' => $nonLeapYear->copy()->setDay($dayOfTheMonth),
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
            ['name' => 'created_at', 'label' => 'Created At', 'formatter' => 'datetime'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);
    $this->assertDatabaseCount('export_schedules', 1);
    $this->assertDatabaseHas($monthlySchedule, ['schedule_frequency' => ScheduleFrequency::MONTHLY]);

    $testTime = $nonLeapYear;
    for ($i = 0; $i < $numOfDays; $i++) {   // run every once every day for a year
        Carbon::setTestNow($testTime);
        $this->artisan('export:run');
        $testTime->addDay();
    }
    Notification::assertCount(12);
    Notification::assertSentTo($monthlySchedule->owner, ScheduledExportCompleteNotification::class);
});

it('sends a monthly export email every month', function () {
    $nonLeapYear = Carbon::createFromDate(2023)->firstOfYear();
    $dayOfTheMonth = 15;
    $numOfDays = $nonLeapYear->diffInDays($nonLeapYear->copy()->endOfYear());

    $monthlySchedule = ExportSchedule::create([
        'name' => 'User Export Monthly (15th)',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::MONTHLY,
        'formats' => [ExportFormat::Csv],
        'schedule_time' => $nonLeapYear->toTimeString(),
        'schedule_day_of_month' => $dayOfTheMonth,
        'next_run_at' => $nonLeapYear->copy()->setDay($dayOfTheMonth),
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
            ['name' => 'created_at', 'label' => 'Created At', 'formatter' => 'datetime'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);
    $this->assertDatabaseCount('export_schedules', 1);
    $this->assertDatabaseHas($monthlySchedule, ['schedule_frequency' => ScheduleFrequency::MONTHLY]);

    $testTime = $nonLeapYear;
    for ($i = 0; $i < $numOfDays; $i++) {   // run every once every day for a year
        Carbon::setTestNow($testTime);
        $this->artisan('export:run');
        $testTime->addDay();
    }
    Notification::assertCount(12);
    Notification::assertSentTo($monthlySchedule->owner, ScheduledExportCompleteNotification::class);
});

it('sends a monthly export email on the last day of every month (31st; 28th/29th/30th for others)', function () {
    $nonLeapYear = Carbon::createFromDate(2023)->firstOfYear();
    $dayOfTheMonth = -1;
    $numOfDays = $nonLeapYear->diffInDays($nonLeapYear->copy()->endOfYear());

    $monthlySchedule = ExportSchedule::create([
        'name' => 'User export Last Day the month',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::MONTHLY,
        'formats' => [ExportFormat::Csv],
        'schedule_time' => $nonLeapYear->toTimeString(),
        'schedule_day_of_month' => $dayOfTheMonth,
        'next_run_at' => $nonLeapYear->copy()->lastOfMonth(),
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
            ['name' => 'created_at', 'label' => 'Created At', 'formatter' => 'datetime'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);
    $this->assertDatabaseCount('export_schedules', 1);
    $this->assertDatabaseHas($monthlySchedule, ['schedule_frequency' => ScheduleFrequency::MONTHLY]);

    $testTime = $nonLeapYear;
    for ($i = 0; $i < $numOfDays; $i++) {   // run every once every day for a year
        Carbon::setTestNow($testTime);
        $this->artisan('export:run');
        $testTime->addDay();
    }
    Notification::assertCount(12);
    Notification::assertSentTo($monthlySchedule->owner, ScheduledExportCompleteNotification::class);
});

it('sends a yearly export email (June 15th)', function () {
    $nonLeapYear =  Carbon::createFromDate(2023)->firstOfYear();
    $dayOfTheMonth = 15;
    $month = Month::JUNE->value;
    $numOfYears = 12;

    $yearlySchedule = ExportSchedule::create([
        'name' => 'User Export Yearly (June 15th)',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::YEARLY,
        'formats' => [ExportFormat::Csv],
        'schedule_time' => $nonLeapYear->toTimeString(),
        'schedule_month' => $month,
        'schedule_day_of_month' => $dayOfTheMonth,
        'next_run_at' => $nonLeapYear->copy()->setMonth($month)->setDay($dayOfTheMonth),
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
            ['name' => 'created_at', 'label' => 'Created At', 'formatter' => 'datetime'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);
    $this->assertDatabaseCount('export_schedules', 1);
    $this->assertDatabaseHas($yearlySchedule, ['schedule_frequency' => ScheduleFrequency::YEARLY]);

    $testTime = $nonLeapYear;
    for ($i = 0; $i < $numOfYears * 3; $i++) {   // run every 4 months every year for 12 years
        Carbon::setTestNow($testTime);
        $this->artisan('export:run');
        $testTime->addMonths(4);
    }
    Notification::assertCount($numOfYears);
    Notification::assertSentTo($yearlySchedule->owner, ScheduledExportCompleteNotification::class);
});

it('sends a yearly export email (February 29th; 28th for non-leap year)', function () {
    $nonLeapYear = Carbon::createFromDate(2024)->firstOfYear();
    $dayOfTheMonth = 29;
    $month = Month::FEBRUARY->value;
    $numOfYears = 12;

    $yearlySchedule = ExportSchedule::create([
        'name' => 'User Export Yearly (June 15th)',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::YEARLY,
        'formats' => [ExportFormat::Csv],
        'schedule_time' => $nonLeapYear->toTimeString(),
        'schedule_month' => $month,
        'schedule_day_of_month' => $dayOfTheMonth,
        'next_run_at' => $nonLeapYear->copy()->setMonth($month)->setDay($dayOfTheMonth),
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
            ['name' => 'created_at', 'label' => 'Created At', 'formatter' => 'datetime'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);
    $this->assertDatabaseCount('export_schedules', 1);
    $this->assertDatabaseHas($yearlySchedule, ['schedule_frequency' => ScheduleFrequency::YEARLY]);

    $testTime = $nonLeapYear;
    for ($i = 0; $i < $numOfYears * 3; $i++) {   // run every 4 months every year for 12 years
        Carbon::setTestNow($testTime);
        $this->artisan('export:run');
        $testTime->addMonths(4);
    }
    Notification::assertCount($numOfYears);
    Notification::assertSentTo($yearlySchedule->owner, ScheduledExportCompleteNotification::class);
});

it('sends a quarterly export email (starts on January 15th)', function () {
    $nonLeapYear = now()->firstOfYear();
    $dayOfTheMonth = 15;
    $startMonth = Month::JANUARY->value;
    $numOfYears = 10;

    $yearlySchedule = ExportSchedule::create([
        'name' => 'User Export Quarterly (January 15th)',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::QUARTERLY,
        'formats' => [ExportFormat::Csv],
        'schedule_time' => $nonLeapYear->toTimeString(),
        'schedule_month' => $startMonth,
        'schedule_day_of_month' => $dayOfTheMonth,
        'next_run_at' => $nonLeapYear->copy()->setMonth($startMonth)->setDay($dayOfTheMonth),
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
            ['name' => 'created_at', 'label' => 'Created At', 'formatter' => 'datetime'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);
    $this->assertDatabaseCount('export_schedules', 1);
    $this->assertDatabaseHas($yearlySchedule, ['schedule_frequency' => ScheduleFrequency::QUARTERLY]);

    $testTime = $nonLeapYear;
    for ($i = 0; $i < $numOfYears * 12; $i++) {   // run every month every year for 10 years
        Carbon::setTestNow($testTime);
        $this->artisan('export:run');
        $testTime->addMonth();
    }
    Notification::assertCount($numOfYears * 4);
    Notification::assertSentTo($yearlySchedule->owner, ScheduledExportCompleteNotification::class);
});

it('sends a quarterly export email (starts on November 29th; February 28th for non-leap year)', function () {
    $nonLeapMonth = Carbon::createFromDate(2023)->setMonth(11)->firstOfMonth();
    $dayOfTheMonth = 29;
    $startMonth = Month::NOVEMBER->value;
    $numOfYears = 10;

    $yearlySchedule = ExportSchedule::create([
        'name' => 'User Export Quarterly (November 29th)',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::QUARTERLY,
        'formats' => [ExportFormat::Csv],
        'schedule_time' => $nonLeapMonth->toTimeString(),
        'schedule_month' => $startMonth,
        'schedule_day_of_month' => $dayOfTheMonth,
        'next_run_at' => $nonLeapMonth->copy()->setMonth($startMonth)->setDay($dayOfTheMonth),
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
            ['name' => 'created_at', 'label' => 'Created At', 'formatter' => 'datetime'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);
    $this->assertDatabaseCount('export_schedules', 1);
    $this->assertDatabaseHas($yearlySchedule, ['schedule_frequency' => ScheduleFrequency::QUARTERLY]);

    $testTime = $nonLeapMonth;
    for ($i = 0; $i < $numOfYears * 12; $i++) {   // run every month every year for 10 years
        Carbon::setTestNow($testTime);
        $this->artisan('export:run');
        $testTime->addMonth();
    }
    Notification::assertCount($numOfYears * 4);
    Notification::assertSentTo($yearlySchedule->owner, ScheduledExportCompleteNotification::class);
});

it('sends a half-yearly export email (starts on January 15th)', function () {
    $nonLeapYear = now()->firstOfYear();
    $dayOfTheMonth = 15;
    $startMonth = Month::JANUARY->value;
    $numOfYears = 10;

    $yearlySchedule = ExportSchedule::create([
        'name' => 'User Export Half-Yearly (January 15th)',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::HALF_YEARLY,
        'formats' => [ExportFormat::Csv],
        'schedule_time' => $nonLeapYear->toTimeString(),
        'schedule_month' => $startMonth,
        'schedule_day_of_month' => $dayOfTheMonth,
        'next_run_at' => $nonLeapYear->copy()->setMonth($startMonth)->setDay($dayOfTheMonth),
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
            ['name' => 'created_at', 'label' => 'Created At', 'formatter' => 'datetime'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);
    $this->assertDatabaseCount('export_schedules', 1);
    $this->assertDatabaseHas($yearlySchedule, ['schedule_frequency' => ScheduleFrequency::HALF_YEARLY]);

    $testTime = $nonLeapYear;
    for ($i = 0; $i < $numOfYears * 12; $i++) {   // run every month every year for 10 years
        Carbon::setTestNow($testTime);
        $this->artisan('export:run');
        $testTime->addMonth();
    }
    Notification::assertCount($numOfYears * 2);
    Notification::assertSentTo($yearlySchedule->owner, ScheduledExportCompleteNotification::class);
});

it('sends a half-yearly export email (starts on August 29th; February 28th for non-leap year)', function () {
    $nonLeapMonth = Carbon::createFromDate(2023)->setMonth(8)->firstOfMonth();
    $dayOfTheMonth = 29;
    $startMonth = Month::AUGUST->value;
    $numOfYears = 10;

    $yearlySchedule = ExportSchedule::create([
        'name' => 'User Export Half-Yearly (November 29th)',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::HALF_YEARLY,
        'formats' => [ExportFormat::Csv],
        'schedule_time' => $nonLeapMonth->toTimeString(),
        'schedule_month' => $startMonth,
        'schedule_day_of_month' => $dayOfTheMonth,
        'next_run_at' => $nonLeapMonth->copy()->setMonth($startMonth)->setDay($dayOfTheMonth),
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
            ['name' => 'created_at', 'label' => 'Created At', 'formatter' => 'datetime'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);
    $this->assertDatabaseCount('export_schedules', 1);
    $this->assertDatabaseHas($yearlySchedule, ['schedule_frequency' => ScheduleFrequency::HALF_YEARLY]);

    $testTime = $nonLeapMonth;
    for ($i = 0; $i < $numOfYears * 12; $i++) {   // run every month every year for 10 years
        Carbon::setTestNow($testTime);
        $this->artisan('export:run');
        $testTime->addMonth();
    }
    Notification::assertCount($numOfYears * 2);
    Notification::assertSentTo($yearlySchedule->owner, ScheduledExportCompleteNotification::class);
});

it('sends a cron export email (quarterly at 2:00 am)', function () {
    $numOfYears = 10;

    $cronSchedule = ExportSchedule::create([
        'name' => 'User Export Cron (Quarterly at 2:00 am)',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::CRON,
        'formats' => [ExportFormat::Csv],
        'next_run_at' => now()->setMonth(3)->setTime(2, 0),
        // at 2:00 am on the 1st of March, June, September, Dec
        'cron' => '0 2 1 3,6,9,12 *',
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
            ['name' => 'created_at', 'label' => 'Created At', 'formatter' => 'datetime'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);
    $this->assertDatabaseCount('export_schedules', 1);
    $this->assertDatabaseHas($cronSchedule, ['schedule_frequency' => ScheduleFrequency::CRON]);

    $testTime = now()->firstOfYear()->setDay(2);
    for ($i = 0; $i < $numOfYears * 12; $i++) {   // run on the second day of every month for 10 years
        Carbon::setTestNow($testTime);
        $this->artisan('export:run');
        $testTime->addMonth();
    }
    Notification::assertCount($numOfYears * 4);
    Notification::assertSentTo($cronSchedule->owner, ScheduledExportCompleteNotification::class);
});

it('sends a cron export email (every weekday at 10:30 am)', function () {
    $firstDayOfTheYear = now()->firstOfYear();
    $firstWeekDayOfTheYear = $firstDayOfTheYear->isWeekend() ? $firstDayOfTheYear->nextWeekday() : $firstDayOfTheYear;
    $lastDayOfTheYear = now()->lastOfYear();
    $lastWeekDayOfTheYear = $lastDayOfTheYear->isWeekend() ? $lastDayOfTheYear->previousWeekday() : $lastDayOfTheYear;
    $numOfWeekDays = $firstWeekDayOfTheYear->diffInWeekdays($lastWeekDayOfTheYear);

    $cronSchedule = ExportSchedule::create([
        'name' => 'User Export Cron (Every weekday at 10:30 am)',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::CRON,
        'formats' => [ExportFormat::Csv],
        'next_run_at' => $firstWeekDayOfTheYear->copy()->setTime(10, 30),
        // every week from Monday to Friday at 10:30am
        'cron' => '30 10 * * 1-5',
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
            ['name' => 'created_at', 'label' => 'Created At', 'formatter' => 'datetime'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);
    $this->assertDatabaseCount('export_schedules', 1);
    $this->assertDatabaseHas($cronSchedule, ['schedule_frequency' => ScheduleFrequency::CRON]);

    $testTime = $firstWeekDayOfTheYear;
    for ($i = 0; $i < $numOfWeekDays; $i++) {   // run on the second day of every month for 10 years
        Carbon::setTestNow($testTime);
        $this->artisan('export:run');
        $testTime->addWeekday();
    }
    Notification::assertCount($numOfWeekDays - 1);
    Notification::assertSentTo($cronSchedule->owner, ScheduledExportCompleteNotification::class);
});
