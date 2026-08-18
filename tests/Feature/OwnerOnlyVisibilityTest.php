<?php

use Filament\Actions\Testing\TestAction;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Visualbuilder\ExportScheduler\Enums\ReportVisibility;
use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource;
use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource\Pages\ListCustomReports;
use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource\Pages\ViewCustomReport;
use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource\RelationManagers\SchedulesRelationManager;
use Visualbuilder\ExportScheduler\Models\CustomReport;
use Visualbuilder\ExportScheduler\Tests\Models\Contact;
use Visualbuilder\ExportScheduler\Tests\Models\User;

use function Pest\Livewire\livewire;

/**
 * QA reported an "Owner Only" report being readable, editable and deletable by
 * another user. The cause was a configured visibility bypass in the host app, not
 * the rules themselves — so these lock the rules down against regression, with the
 * bypass left at its package default of nobody.
 *
 * {@see VisibilityBypassTest} covers the privileged half.
 */
function ownerOnlyReport(User $owner): CustomReport
{
    return CustomReport::factory()->create([
        'owner_id' => $owner->id,
        'owner_type' => User::class,
        'visibility' => ReportVisibility::OWNER,
    ]);
}

it('hides an owner-only report from the visibleTo scope for everyone but its owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $report = ownerOnlyReport($owner);

    expect(CustomReport::visibleTo($owner)->pluck('id')->all())->toEqual([$report->id]);
    expect(CustomReport::visibleTo($other)->count())->toBe(0);
});

it('keeps an owner-only report off the list table for a non-owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $report = ownerOnlyReport($owner);
    $ownedByOther = ownerOnlyReport($other);

    $this->actingAs($other);

    livewire(ListCustomReports::class)
        ->assertCanSeeTableRecords([$ownedByOther])
        ->assertCanNotSeeTableRecords([$report]);
});

it('refuses view, edit and delete of an owner-only report to a non-owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $report = ownerOnlyReport($owner);

    $this->actingAs($other);

    expect(CustomReportResource::canView($report))->toBeFalse();
    expect(CustomReportResource::canEdit($report))->toBeFalse();
    expect(CustomReportResource::canDelete($report))->toBeFalse();
});

it('grants view, edit and delete of an owner-only report to its owner', function () {
    $owner = User::factory()->create();

    $report = ownerOnlyReport($owner);

    $this->actingAs($owner);

    expect(CustomReportResource::canView($report))->toBeTrue();
    expect(CustomReportResource::canEdit($report))->toBeTrue();
    expect(CustomReportResource::canDelete($report))->toBeTrue();
});

it('will not resolve an owner-only report on the view page for a non-owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $report = ownerOnlyReport($owner);

    $this->actingAs($other);

    // The resource scopes every page through visibleTo(), so the record cannot
    // even be resolved from the URL — a report you cannot see does not exist.
    livewire(ViewCustomReport::class, ['record' => $report->getKey()]);
})->throws(ModelNotFoundException::class);

it('hides the schedules panel of an owner-only report from a non-owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $report = ownerOnlyReport($owner);

    $this->actingAs($other);
    expect(SchedulesRelationManager::canViewForRecord($report, ViewCustomReport::class))->toBeFalse();

    $this->actingAs($owner);
    expect(SchedulesRelationManager::canViewForRecord($report, ViewCustomReport::class))->toBeTrue();
});

it('hides the edit and delete row actions on an owner-only report from a non-owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    // Shared for viewing, so the row is on screen at all — but still not editable.
    $report = CustomReport::factory()->create([
        'owner_id' => $owner->id,
        'owner_type' => User::class,
        'visibility' => ReportVisibility::USER_TYPE,
        'visible_to_type' => User::class,
    ]);

    $this->actingAs($other);

    livewire(ListCustomReports::class)
        ->assertCanSeeTableRecords([$report])
        ->assertActionHidden(TestAction::make('edit')->table($report))
        ->assertActionHidden(TestAction::make('delete')->table($report));
});

it('does not let a matching id on another user class stand in for the owner', function () {
    $owner = User::factory()->create();

    // Same primary key, different class. Ownership must compare both.
    $impostor = Contact::create(['full_name' => 'Impostor']);
    $impostor->forceFill(['id' => $owner->id])->save();

    $report = ownerOnlyReport($owner);

    expect($report->isOwnedBy($impostor))->toBeFalse();
    expect($report->isVisibleTo($impostor))->toBeFalse();
});

it('does not let a named user of another class see a report shared with a different class', function () {
    $owner = User::factory()->create();
    $named = User::factory()->create();

    $report = CustomReport::factory()->create([
        'owner_id' => $owner->id,
        'owner_type' => User::class,
        'visibility' => ReportVisibility::NAMED_USERS,
        'visible_to_type' => User::class,
        'visible_to_ids' => [(string) $named->id],
    ]);

    $impostor = Contact::create(['full_name' => 'Impostor']);
    $impostor->forceFill(['id' => $named->id])->save();

    expect($report->isVisibleTo($named))->toBeTrue();
    expect($report->isVisibleTo($impostor))->toBeFalse();
});

it('shows a guest nothing at all', function () {
    ownerOnlyReport(User::factory()->create());

    expect(CustomReport::visibleTo(null)->count())->toBe(0);
});
