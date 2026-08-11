<?php

use Visualbuilder\ExportScheduler\Enums\ReportVisibility;
use Visualbuilder\ExportScheduler\Models\CustomReport;
use Visualbuilder\ExportScheduler\Models\ScheduledReport;
use Visualbuilder\ExportScheduler\Tests\Models\Contact;
use Visualbuilder\ExportScheduler\Tests\Models\User;

it('reports are owned by their owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $report = CustomReport::factory()->create([
        'owner_id' => $owner->id,
        'owner_type' => User::class,
    ]);

    expect($report)
        ->isOwnedBy(null)->toBeFalse()
        ->isOwnedBy($other)->toBeFalse()
        ->isOwnedBy($owner)->toBeTrue();
});

it('respects owner visibility', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $report = CustomReport::factory()->create([
        'owner_id' => $owner->id,
        'owner_type' => User::class,
        'visibility' => ReportVisibility::OWNER,
    ]);

    expect($report)
        ->isVisibleTo($other)->toBeFalse()
        ->isVisibleTo($owner)->toBeTrue();
});

it('respects user type visibility', function () {
    $owner = User::factory()->create();
    $same_class = User::factory()->create();

    $report = CustomReport::factory()->create([
        'owner_id' => $owner->id,
        'owner_type' => User::class,
        'visibility' => ReportVisibility::USER_TYPE,
        'visible_to_type' => User::class,
    ]);

    expect($report)
        ->isVisibleTo($same_class)->toBeTrue()
        ->isVisibleTo($owner)->toBeTrue();
});

it('respects named users visibility', function () {
    $owner = User::factory()->create();
    $allowed = User::factory()->create();
    $other = User::factory()->create();

    $report = CustomReport::factory()->create([
        'owner_id' => $owner->id,
        'owner_type' => User::class,
        'visibility' => ReportVisibility::NAMED_USERS,
        'visible_to_type' => User::class,
        'visible_to_ids' => [(string) $allowed->id],
    ]);

    expect($report)
        ->isVisibleTo($allowed)->toBeTrue()
        ->isVisibleTo($other)->toBeFalse()
        ->isVisibleTo($owner)->toBeTrue();
});

it('keeps new ids when changing type and ids together', function () {
    $owner = User::factory()->create();
    $stale = User::factory()->create();
    $chosen = User::factory()->create();

    $report = CustomReport::factory()->create([
        'owner_id' => $owner->id,
        'owner_type' => User::class,
        'visibility' => ReportVisibility::NAMED_USERS,
        'visible_to_type' => User::class,
        'visible_to_ids' => [(string) $stale->id],
    ]);

    $report->update([
        'visible_to_type' => Contact::class,
        'visible_to_ids' => [(string) $chosen->id],
    ]);

    expect($report->fresh()->visible_to_ids)->toEqual([(string) $chosen->id]);
});

it('clears stale ids when changing type alone', function () {
    $stale = User::factory()->create();

    $report = CustomReport::factory()->create([
        'visibility' => ReportVisibility::NAMED_USERS,
        'visible_to_type' => User::class,
        'visible_to_ids' => [(string) $stale->id],
    ]);

    $report->update(['visible_to_type' => Contact::class]);

    expect($report->fresh()->visible_to_ids)->toEqual([]);
});

it('stores ids as strings', function () {
    $user = User::factory()->create();

    $report = CustomReport::factory()->create([
        'visibility' => ReportVisibility::NAMED_USERS,
        'visible_to_type' => User::class,
        'visible_to_ids' => [$user->id],
    ]);

    expect($report->fresh()->visible_to_ids)->toEqual([(string) $user->id]);
});

it('has many schedules', function () {
    $report = CustomReport::factory()->create();
    $schedule = ScheduledReport::factory()->create(['custom_report_id' => $report->id]);

    expect($report->schedules()->count())->toBe(1);
    expect($report->schedules()->first()->is($schedule))->toBeTrue();
});

it('scopes visible reports to user', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    CustomReport::factory()->create([
        'owner_id' => $user1->id,
        'owner_type' => User::class,
        'visibility' => ReportVisibility::OWNER,
    ]);

    CustomReport::factory()->create([
        'owner_id' => $user2->id,
        'owner_type' => User::class,
        'visibility' => ReportVisibility::USER_TYPE,
        'visible_to_type' => User::class,
    ]);

    $visible = CustomReport::visibleTo($user1)->get();
    expect($visible->count())->toBeGreaterThanOrEqual(1);
});
