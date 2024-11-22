<?php

use Carbon\Carbon;
use Filament\Actions\Exports\Enums\ExportFormat;
use Illuminate\Support\Facades\Mail;
use VisualBuilder\ExportScheduler\Enums\ScheduleFrequency;
use VisualBuilder\ExportScheduler\Filament\Exporters\UserExporter;
use VisualBuilder\ExportScheduler\Models\ExportSchedule;
use VisualBuilder\ExportScheduler\Tests\Models\User;


it('sends a daily export email for 365 days', function () {
    Mail::fake();

    //Load a record from already seeded db
//    $exportSchedule = ExportSchedule::where('schedule_frequency', ScheduleFrequency::DAILY)->first();

    //Or create one
    $exportSchedule = ExportSchedule::create(  // DAILY Export Schedule
        [
            'name'                  => 'User Export Daily',
            'exporter'              => UserExporter::class,
            'schedule_frequency'    => ScheduleFrequency::DAILY,
            'cron'                  => null,
            'formats'               => json_encode([ExportFormat::Csv]),
            'schedule_time'         => '03:00:00', // Runs daily at 3:00 AM
            'schedule_month'        => null,
            'schedule_day_of_week'  => null,
            'schedule_day_of_month' => null,
            'schedule_start_month'  => null,
            'columns'               => json_encode([
                ['name' => 'id', 'label' => 'ID'],
                ['name' => 'email', 'label' => 'Email'],
                ['name' => 'created_at', 'label' => 'Created At', 'formatter' => 'datetime'],
            ]),
            'owner_id'              => 1, // Replace with the actual owner ID
            'owner_type'            => User::class,
        ],);

    $this->assertDatabaseCount('export_schedules', 1);
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseEmpty('exports');
    $this->assertNotNull($exportSchedule);


    // Set time to just before the scheduled execution time on the current day
    $testTime = Carbon::now()->addDays(3)->setTime(5, 59, 0); // Set to 2:59 AM
    Carbon::setTestNow($testTime);

    // Run the export command
    $this->artisan('export:run');





    Mail::assertNothingSent();
    $testTime = Carbon::now()->addDay()->setTime(3, 59, 0);
    Carbon::setTestNow($testTime);

    $this->artisan('export:run');

//
//
//
//        // Advance time to just after the scheduled time
//        Carbon::setTestNow($testTime->addMinutes(2)); // Add 2 minutes (3:01 AM)
//
//        $this->artisan('export:run');
//
//        // Assertions for the current day
//        Mail::assertSent(ExportReady::class, function ($mail) use ($exportSchedule) {
//            return $mail->hasTo($exportSchedule->owner->email) && $mail->exportSchedule->id === $exportSchedule->id;
//        });

});

/*

// Weekly (Monday)
it('sends a weekly export email', function () {
    $exportSchedule = ExportSchedule::where('schedule_frequency', ScheduleFrequency::WEEKLY)->first();
    $this->assertNotNull($exportSchedule);

    // Test across a range of dates to cover different starting days of the week
    $startDate = Carbon::now()->startOfWeek();  // Start of the current week
    for ($i = 0; $i < 10; $i++) { // Test for 10 weeks
        $testTime = $startDate->addDays($i * 7)->setTime(3, 59, 0);  // 3:59 AM on the target day of the week
        Carbon::setTestNow($testTime);

        Mail::fake();
        $this->artisan('export:run');

        Carbon::setTestNow($testTime->addMinutes(2));  //4.01 am

        $this->artisan('export:run');

        Mail::assertSent(ExportReady::class, function ($mail) use ($exportSchedule) {
            return $mail->hasTo($exportSchedule->owner->email) && $mail->exportSchedule->id === $exportSchedule->id;
        });

    }
});



// Monthly (15th)
it('sends a monthly export email (15th)', function () {
    $exportSchedule = ExportSchedule::where('schedule_frequency', ScheduleFrequency::MONTHLY)->where('schedule_day_of_month',15)->first();
    $this->assertNotNull($exportSchedule);

    //Test for 12 months
    for ($i = 0; $i < 12; $i++) {
        $testTime = Carbon::now()->addMonths($i)->setDay(15)->setTime(4, 59, 0); //4:59 on 15th
        Carbon::setTestNow($testTime);

        Mail::fake();
        $this->artisan('export:run');

        Carbon::setTestNow($testTime->addMinutes(2)); //5.01 on 15th

        $this->artisan('export:run');
        Mail::assertSent(ExportReady::class, function ($mail) use ($exportSchedule) {
            return $mail->hasTo($exportSchedule->owner->email) && $mail->exportSchedule->id === $exportSchedule->id;
        });

    }
});


/*

// Test for MONTHLY Last Day of Month
it('sends a monthly export email for the last day of the month', function () {
    $exportSchedule = ExportSchedule::where('schedule_frequency', ScheduleFrequency::MONTHLY)->where('schedule_day_of_month', -1)->first();
    $this->assertNotNull($exportSchedule);

    // Test for 12 months
    for ($i = 0; $i < 12; $i++) {
        // Using ->endOfMonth() to get the last day dynamically
        $testTime = Carbon::now()->addMonths($i)->endOfMonth()->setTime(5, 59, 0); //5.59 last day
        Carbon::setTestNow($testTime);
        Mail::fake();
        $this->artisan('export:run');

        Carbon::setTestNow($testTime->addMinutes(2)); //6.01 last day
        $this->artisan('export:run');

        Mail::assertSent(ExportReady::class, function ($mail) use ($exportSchedule) {
            return $mail->hasTo($exportSchedule->owner->email) && $mail->exportSchedule->id === $exportSchedule->id;
        });
    }
});



// Yearly
it('sends a yearly export email', function () {
    $exportSchedule = ExportSchedule::where('schedule_frequency', ScheduleFrequency::YEARLY)->first();
    $this->assertNotNull($exportSchedule);

    $testTime = Carbon::now()->setTime(6, 59, 0); //6:59 AM on Jan 1st
    Carbon::setTestNow($testTime);

    Mail::fake();
    $this->artisan('export:run');

    Carbon::setTestNow($testTime->addMinutes(2)); //7.01 AM on Jan 1st
    $this->artisan('export:run');

    Mail::assertSent(ExportReady::class, function ($mail) use ($exportSchedule) {
        return $mail->hasTo($exportSchedule->owner->email) && $mail->exportSchedule->id === $exportSchedule->id;
    });

});



// Leap Year
it('sends a yearly export email (leap year)', function () {
    $exportSchedule = ExportSchedule::where('schedule_frequency', ScheduleFrequency::YEARLY)
        ->where('schedule_month', 2) // February
        ->where('schedule_day_of_month', 29) // 29th (leap day)
        ->first();
    $this->assertNotNull($exportSchedule);

    // Set to a leap year (e.g., 2024)
    $testTime = Carbon::create(2024)->setTime(9, 59, 0);  // Set time to just before execution

    Carbon::setTestNow($testTime);
    Mail::fake();
    $this->artisan('export:run');

    Carbon::setTestNow($testTime->addMinutes(2));

    $this->artisan('export:run');

    Mail::assertSent(ExportReady::class, function ($mail) use ($exportSchedule) {
        return $mail->hasTo($exportSchedule->owner->email) && $mail->exportSchedule->id === $exportSchedule->id;
    });

    // Test non-leap year behavior (e.g., 2023) —  adjust assertion as needed
    $testTime = Carbon::create(2023)->setTime(9, 59, 0); // Set to a non-leap year
    Carbon::setTestNow($testTime);
    Mail::fake(); // Reset fake mail
    $this->artisan('export:run');
    Carbon::setTestNow($testTime->addMinutes(2));
    $this->artisan('export:run');
    Mail::assertNothingSent();  // or assert different behavior depending on your logic for non-leap years
});




// Quarterly (Jan 10)
it('sends a quarterly export email', function () {
    $exportSchedule = ExportSchedule::where('schedule_frequency', ScheduleFrequency::QUARTERLY)->first();
    $this->assertNotNull($exportSchedule);

    $startMonth = $exportSchedule->schedule_start_month; // Get the starting month from the schedule
    $startDay = $exportSchedule->schedule_day_of_month;

    for ($i = 0; $i < 4; $i++) { // Loop through four quarters
        $testTime = Carbon::create(2024, $startMonth, $startDay, 7, 59, 0)->addQuarters($i); // 7:59 AM on start day/month + quarters
        Carbon::setTestNow($testTime);

        Mail::fake();
        $this->artisan('export:run');

        Carbon::setTestNow($testTime->addMinutes(2)); // 8:01 AM
        $this->artisan('export:run');

        Mail::assertSent(ExportReady::class, function ($mail) use ($exportSchedule) {
            return $mail->hasTo($exportSchedule->owner->email) && $mail->exportSchedule->id === $exportSchedule->id;
        });

    }
});





// Half-Yearly (July 1)
it('sends a half-yearly export email', function () {
    $exportSchedule = ExportSchedule::where('schedule_frequency', ScheduleFrequency::HALF_YEARLY)->first();
    $this->assertNotNull($exportSchedule);

    $startMonth = $exportSchedule->schedule_start_month; // Get the starting month from the schedule
    $startDay = $exportSchedule->schedule_day_of_month;
    for ($i = 0; $i < 2; $i++) { // Test for two half-year periods

        $testTime = Carbon::create(2024, $startMonth, $startDay, 8, 59, 0)->addMonths($i * 6);  // 8.59 on start day/month + half years
        Carbon::setTestNow($testTime);

        Mail::fake();
        $this->artisan('export:run');

        Carbon::setTestNow($testTime->addMinutes(2));// 9.01 on start day/month + half years
        $this->artisan('export:run');

        Mail::assertSent(ExportReady::class, function ($mail) use ($exportSchedule) {
            return $mail->hasTo($exportSchedule->owner->email) && $mail->exportSchedule->id === $exportSchedule->id;
        });

    }
});



// Cron (Weekdays 10:30 AM)
it('sends a cron export email (weekdays)', function () {
    $exportSchedule = ExportSchedule::where('schedule_frequency', ScheduleFrequency::CRON)
        ->where('cron', '30 10 * * 1-5')
        ->first();

    $this->assertNotNull($exportSchedule);

    $testDate = Carbon::create(2024, 1, 7, 10, 29, 0); // a Sunday
    Carbon::setTestNow($testDate);
    Mail::fake();

    $this->artisan('export:run');
    Carbon::setTestNow($testDate->addMinute());  //10.30 am on monday
    $this->artisan('export:run');

    Mail::assertSent(ExportReady::class, function ($mail) use ($exportSchedule) {
        return $mail->hasTo($exportSchedule->owner->email) && $mail->exportSchedule->id === $exportSchedule->id;
    });
});


it('sends a cron export email (quarter ends)', function () {
    $exportSchedule = ExportSchedule::where('schedule_frequency', ScheduleFrequency::CRON)
        ->where('cron', '0 2 1 3,6,9,12 *')
        ->first();

    $this->assertNotNull($exportSchedule);

    $targetMonths = [3, 6, 9, 12]; // March, June, September, December

    foreach ($targetMonths as $month) {
        $testTime = Carbon::create(2024, $month, 1, 1, 59, 0); // 1:59 AM on the 1st of the target month
        Carbon::setTestNow($testTime);

        Mail::fake();
        $this->artisan('export:run');

        Carbon::setTestNow($testTime->addMinutes(2));  //2.01 am on the 1st of target month

        $this->artisan('export:run');

        Mail::assertSent(ExportReady::class, function ($mail) use ($exportSchedule) {
            return $mail->hasTo($exportSchedule->owner->email) && $mail->exportSchedule->id === $exportSchedule->id;
        });
    }
});

*/
