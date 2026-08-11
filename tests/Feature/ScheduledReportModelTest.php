<?php

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

it('uses schedule format override over report formats', function () {
    $schedule = ScheduledReport::factory()->create([
        'custom_report_id' => CustomReport::factory()
            ->create(['formats' => ['csv']])
            ->id,
        'formats' => ['xlsx'],
    ]);

    expect($schedule->resolved_formats)->toEqual(['xlsx']);
});

it('falls back to report formats when schedule has none', function () {
    $schedule = ScheduledReport::factory()->create([
        'custom_report_id' => CustomReport::factory()
            ->create(['formats' => ['csv']])
            ->id,
        'formats' => null,
    ]);

    expect($schedule->resolved_formats)->toEqual(['csv']);
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
