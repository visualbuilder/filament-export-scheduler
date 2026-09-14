<?php

use Visualbuilder\ExportScheduler\Enums\DayOfWeek;
use Visualbuilder\ExportScheduler\Enums\Month;
use Visualbuilder\ExportScheduler\Enums\ScheduleFrequency;
use Visualbuilder\ExportScheduler\Models\CustomReport;
use Visualbuilder\ExportScheduler\Models\ScheduledReport;
use Visualbuilder\ExportScheduler\Tests\Models\Contact;
use Visualbuilder\ExportScheduler\Tests\Models\User;

it('has report relationship', function () {
    $report = CustomReport::factory()->create();
    $schedule = ScheduledReport::factory()->create(['custom_report_id' => $report->id]);

    expect($schedule->report()->first()->is($report))->toBeTrue();
});

it('has recipient morph relationship', function () {
    $recipient = User::factory()->create();
    $schedule = ScheduledReport::factory()->create([
        'recipient_id' => $recipient->id,
        'recipient_type' => User::class,
    ]);

    expect($schedule->recipient()->first()->is($recipient))->toBeTrue();
});

it('counts cc users', function () {
    $schedule = ScheduledReport::factory()->create([
        'cc' => [(string) User::factory()->create()->id, (string) User::factory()->create()->id],
    ]);

    expect($schedule->cc_count)->toBe(2);
});

it('scopes enabled schedules', function () {
    ScheduledReport::factory()->create(['enabled' => true]);
    ScheduledReport::factory()->create(['enabled' => false]);

    expect(ScheduledReport::enabled()->get())
        ->count()->toBe(1)
        ->first()->enabled->toBeTrue();
});

it('calculates next run time for daily schedule', function () {
    $schedule = ScheduledReport::factory()->create([
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => '09:00:00',
    ]);

    expect($schedule->next_run_at)->not->toBeNull();
});

it('resolves the formats set on the schedule', function () {
    $schedule = ScheduledReport::factory()->create([
        'custom_report_id' => CustomReport::factory()->create()->id,
        'formats' => ['xlsx'],
    ]);

    expect($schedule->resolved_formats)->toEqual(['xlsx']);
});

it('does not inherit formats from the report', function () {
    $schedule = ScheduledReport::factory()->create([
        'custom_report_id' => CustomReport::factory()->create()->id,
        'formats' => null,
    ]);

    expect($schedule->resolved_formats)->toEqual([]);
});

it('clears cc when changing recipient type', function () {
    $schedule = ScheduledReport::factory()->create([
        'recipient_type' => User::class,
        'cc' => [(string) User::factory()->create()->id],
    ]);

    expect($schedule->cc)->not->toBeEmpty();

    $schedule->update(['recipient_type' => Contact::class]);

    expect($schedule->fresh()->cc)->toBeEmpty();
});

it('keeps new cc when changing recipient type and cc together', function () {
    $stale = User::factory()->create();
    $chosen = User::factory()->create();

    $schedule = ScheduledReport::factory()->create([
        'recipient_type' => User::class,
        'cc' => [(string) $stale->id],
    ]);

    $schedule->update([
        'recipient_type' => Contact::class,
        'cc' => [(string) $chosen->id],
    ]);

    expect($schedule->fresh()->cc)->toEqual([(string) $chosen->id]);
});

it('leaves cc intact when changing recipient id alone', function () {
    $cc = User::factory()->create();
    $newRecipient = User::factory()->create();

    $schedule = ScheduledReport::factory()->create([
        'recipient_type' => User::class,
        'cc' => [(string) $cc->id],
    ]);

    $schedule->update(['recipient_id' => $newRecipient->id]);

    expect($schedule->fresh()->cc)->toEqual([(string) $cc->id]);
});

/*
|--------------------------------------------------------------------------
| schedule_summary
|--------------------------------------------------------------------------
|
| One line of words per schedule so the reports list can show when things
| run without opening each report. The timezone is only appended when it
| differs from the application's.
*/

it('summarises a daily schedule', function () {
    $schedule = ScheduledReport::factory()->create([
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => '19:00:00',
        'schedule_timezone' => config('app.timezone'),
    ]);

    expect($schedule->schedule_summary)->toBe('Daily at 19:00');
});

it('summarises a weekly schedule with its day', function () {
    $schedule = ScheduledReport::factory()->create([
        'schedule_frequency' => ScheduleFrequency::WEEKLY,
        'schedule_day_of_week' => DayOfWeek::MONDAY,
        'schedule_time' => '09:00:00',
        'schedule_timezone' => config('app.timezone'),
    ]);

    expect($schedule->schedule_summary)->toBe('Weekly on Monday at 09:00');
});

it('summarises a monthly schedule on a fixed day and on the last day', function () {
    $fixed = ScheduledReport::factory()->create([
        'schedule_frequency' => ScheduleFrequency::MONTHLY,
        'schedule_day_of_month' => 1,
        'schedule_time' => '09:00:00',
        'schedule_timezone' => config('app.timezone'),
    ]);

    $last = ScheduledReport::factory()->create([
        'schedule_frequency' => ScheduleFrequency::MONTHLY,
        'schedule_day_of_month' => -1,
        'schedule_time' => '09:00:00',
        'schedule_timezone' => config('app.timezone'),
    ]);

    expect($fixed->schedule_summary)->toBe('Monthly on day 1 at 09:00')
        ->and($last->schedule_summary)->toBe('Monthly on the last day at 09:00');
});

it('summarises a quarterly schedule with its starting month', function () {
    $schedule = ScheduledReport::factory()->create([
        'schedule_frequency' => ScheduleFrequency::QUARTERLY,
        'schedule_month' => Month::JANUARY,
        'schedule_day_of_month' => 1,
        'schedule_time' => '09:00:00',
        'schedule_timezone' => config('app.timezone'),
    ]);

    expect($schedule->schedule_summary)->toBe('Quarterly from January 1 at 09:00');
});

it('summarises a cron schedule with its expression', function () {
    $schedule = ScheduledReport::factory()->create([
        'schedule_frequency' => ScheduleFrequency::CRON,
        'cron' => '0 9 * * *',
        'schedule_timezone' => config('app.timezone'),
    ]);

    expect($schedule->schedule_summary)->toBe('Cron 0 9 * * *');
});

it('appends the timezone only when it differs from the application timezone', function () {
    $other = ScheduledReport::factory()->create([
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => '19:00:00',
        'schedule_timezone' => 'Europe/London',
    ]);

    config(['app.timezone' => 'Europe/London']);

    expect($other->schedule_summary)->toBe('Daily at 19:00');

    config(['app.timezone' => 'UTC']);

    expect($other->schedule_summary)->toBe('Daily at 19:00 (Europe/London)');
});
