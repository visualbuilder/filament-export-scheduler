<?php

use Carbon\Carbon;
use Filament\Actions\Exports\Enums\ExportFormat;
use Illuminate\Support\Facades\Notification;
use VisualBuilder\ExportScheduler\Enums\DayOfWeek;
use VisualBuilder\ExportScheduler\Enums\ScheduleFrequency;
use VisualBuilder\ExportScheduler\Filament\Exporters\UserExporter;
use VisualBuilder\ExportScheduler\Models\ExportSchedule;
use VisualBuilder\ExportScheduler\Notifications\ScheduledExportCompleteNotification;
use VisualBuilder\ExportScheduler\Tests\Traits\CustomRefreshDatabase;

uses(CustomRefreshDatabase::class);

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
    $wednesday = now()->weekday(\Carbon\WeekDay::Wednesday);
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

it('sends a monthly export email every month (15th) for a year', function () {
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

it('sends a monthly export email every month (29th) for a year in a non-leap year', function () {
    $nonLeapYear = Carbon::createFromDate(2023)->firstOfYear();
    $dayOfTheMonth = 29;
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

it('sends a monthly export email every month (30th) for a year in a non-leap year', function () {
    $nonLeapYear = Carbon::createFromDate(2023)->firstOfYear();
    $dayOfTheMonth = 30;
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

it('sends a monthly export email every month (31st) for a year in a non-leap year', function () {
    $nonLeapYear = Carbon::createFromDate(2023)->firstOfYear();
    $dayOfTheMonth = 31;
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

// Test for MONTHLY Last Day of Month
//it('sends a monthly export email for the last day of the month', function () {
//    $exportSchedule = ExportSchedule::where('schedule_frequency', ScheduleFrequency::MONTHLY)->where('schedule_day_of_month', -1)->first();
//    $this->assertNotNull($exportSchedule);
//
//    // Test for 12 months
//    for ($i = 0; $i < 12; $i++) {
//        // Using ->endOfMonth() to get the last day dynamically
//        $testTime = Carbon::now()->addMonths($i)->endOfMonth()->setTime(5, 59, 0); //5.59 last day
//        Carbon::setTestNow($testTime);
//        Mail::fake();
//        $this->artisan('export:run');
//
//        Carbon::setTestNow($testTime->addMinutes(2)); //6.01 last day
//        $this->artisan('export:run');
//
//        Mail::assertSent(ExportReady::class, function ($mail) use ($exportSchedule) {
//            return $mail->hasTo($exportSchedule->owner->email) && $mail->exportSchedule->id === $exportSchedule->id;
//        });
//    }
//});
//
//
//
// Yearly
//it('sends a yearly export email', function () {
//    $exportSchedule = ExportSchedule::where('schedule_frequency', ScheduleFrequency::YEARLY)->first();
//    $this->assertNotNull($exportSchedule);
//
//    $testTime = Carbon::now()->setTime(6, 59, 0); //6:59 AM on Jan 1st
//    Carbon::setTestNow($testTime);
//
//    Mail::fake();
//    $this->artisan('export:run');
//
//    Carbon::setTestNow($testTime->addMinutes(2)); //7.01 AM on Jan 1st
//    $this->artisan('export:run');
//
//    Mail::assertSent(ExportReady::class, function ($mail) use ($exportSchedule) {
//        return $mail->hasTo($exportSchedule->owner->email) && $mail->exportSchedule->id === $exportSchedule->id;
//    });
//
//});
//
//
//
// Leap Year
//it('sends a yearly export email (leap year)', function () {
//    $exportSchedule = ExportSchedule::where('schedule_frequency', ScheduleFrequency::YEARLY)
//        ->where('schedule_month', 2) // February
//        ->where('schedule_day_of_month', 29) // 29th (leap day)
//        ->first();
//    $this->assertNotNull($exportSchedule);
//
//    // Set to a leap year (e.g., 2024)
//    $testTime = Carbon::create(2024)->setTime(9, 59, 0);  // Set time to just before execution
//
//    Carbon::setTestNow($testTime);
//    Mail::fake();
//    $this->artisan('export:run');
//
//    Carbon::setTestNow($testTime->addMinutes(2));
//
//    $this->artisan('export:run');
//
//    Mail::assertSent(ExportReady::class, function ($mail) use ($exportSchedule) {
//        return $mail->hasTo($exportSchedule->owner->email) && $mail->exportSchedule->id === $exportSchedule->id;
//    });
//
//    // Test non-leap year behavior (e.g., 2023) —  adjust assertion as needed
//    $testTime = Carbon::create(2023)->setTime(9, 59, 0); // Set to a non-leap year
//    Carbon::setTestNow($testTime);
//    Mail::fake(); // Reset fake mail
//    $this->artisan('export:run');
//    Carbon::setTestNow($testTime->addMinutes(2));
//    $this->artisan('export:run');
//    Mail::assertNothingSent();  // or assert different behavior depending on your logic for non-leap years
//});
//
//
//
//
// Quarterly (Jan 10)
//it('sends a quarterly export email', function () {
//    $exportSchedule = ExportSchedule::where('schedule_frequency', ScheduleFrequency::QUARTERLY)->first();
//    $this->assertNotNull($exportSchedule);
//
//    $startMonth = $exportSchedule->schedule_start_month; // Get the starting month from the schedule
//    $startDay = $exportSchedule->schedule_day_of_month;
//
//    for ($i = 0; $i < 4; $i++) { // Loop through four quarters
//        $testTime = Carbon::create(2024, $startMonth, $startDay, 7, 59, 0)->addQuarters($i); // 7:59 AM on start day/month + quarters
//        Carbon::setTestNow($testTime);
//
//        Mail::fake();
//        $this->artisan('export:run');
//
//        Carbon::setTestNow($testTime->addMinutes(2)); // 8:01 AM
//        $this->artisan('export:run');
//
//        Mail::assertSent(ExportReady::class, function ($mail) use ($exportSchedule) {
//            return $mail->hasTo($exportSchedule->owner->email) && $mail->exportSchedule->id === $exportSchedule->id;
//        });
//
//    }
//});
//
//
//
//
//
//// Half-Yearly (July 1)
//it('sends a half-yearly export email', function () {
//    $exportSchedule = ExportSchedule::where('schedule_frequency', ScheduleFrequency::HALF_YEARLY)->first();
//    $this->assertNotNull($exportSchedule);
//
//    $startMonth = $exportSchedule->schedule_start_month; // Get the starting month from the schedule
//    $startDay = $exportSchedule->schedule_day_of_month;
//    for ($i = 0; $i < 2; $i++) { // Test for two half-year periods
//
//        $testTime = Carbon::create(2024, $startMonth, $startDay, 8, 59, 0)->addMonths($i * 6);  // 8.59 on start day/month + half years
//        Carbon::setTestNow($testTime);
//
//        Mail::fake();
//        $this->artisan('export:run');
//
//        Carbon::setTestNow($testTime->addMinutes(2));// 9.01 on start day/month + half years
//        $this->artisan('export:run');
//
//        Mail::assertSent(ExportReady::class, function ($mail) use ($exportSchedule) {
//            return $mail->hasTo($exportSchedule->owner->email) && $mail->exportSchedule->id === $exportSchedule->id;
//        });
//
//    }
//});
//
//
//
//// Cron (Weekdays 10:30 AM)
//it('sends a cron export email (weekdays)', function () {
//    $exportSchedule = ExportSchedule::where('schedule_frequency', ScheduleFrequency::CRON)
//        ->where('cron', '30 10 * * 1-5')
//        ->first();
//
//    $this->assertNotNull($exportSchedule);
//
//    $testDate = Carbon::create(2024, 1, 7, 10, 29, 0); // a Sunday
//    Carbon::setTestNow($testDate);
//    Mail::fake();
//
//    $this->artisan('export:run');
//    Carbon::setTestNow($testDate->addMinute());  //10.30 am on monday
//    $this->artisan('export:run');
//
//    Mail::assertSent(ExportReady::class, function ($mail) use ($exportSchedule) {
//        return $mail->hasTo($exportSchedule->owner->email) && $mail->exportSchedule->id === $exportSchedule->id;
//    });
//});
//
//
//it('sends a cron export email (quarter ends)', function () {
//    $exportSchedule = ExportSchedule::where('schedule_frequency', ScheduleFrequency::CRON)
//        ->where('cron', '0 2 1 3,6,9,12 *')
//        ->first();
//
//    $this->assertNotNull($exportSchedule);
//
//    $targetMonths = [3, 6, 9, 12]; // March, June, September, December
//
//    foreach ($targetMonths as $month) {
//        $testTime = Carbon::create(2024, $month, 1, 1, 59, 0); // 1:59 AM on the 1st of the target month
//        Carbon::setTestNow($testTime);
//
//        Mail::fake();
//        $this->artisan('export:run');
//
//        Carbon::setTestNow($testTime->addMinutes(2));  //2.01 am on the 1st of target month
//
//        $this->artisan('export:run');
//
//        Mail::assertSent(ExportReady::class, function ($mail) use ($exportSchedule) {
//            return $mail->hasTo($exportSchedule->owner->email) && $mail->exportSchedule->id === $exportSchedule->id;
//        });
//    }
//});
