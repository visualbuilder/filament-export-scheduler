<?php

use Filament\Actions\Testing\TestAction;
use Illuminate\Database\Eloquent\Model;
use Visualbuilder\ExportScheduler\Contracts\BypassesReportVisibility;
use Visualbuilder\ExportScheduler\Enums\ReportVisibility;
use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource;
use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource\Pages\ListCustomReports;
use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource\Pages\ViewCustomReport;
use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource\RelationManagers\SchedulesRelationManager;
use Visualbuilder\ExportScheduler\Filament\Actions\Tables\RunExport;
use Visualbuilder\ExportScheduler\Filament\Resources\ScheduledReportResource;
use Visualbuilder\ExportScheduler\Filament\Resources\ScheduledReportResource\Pages\ListScheduledReports;
use Visualbuilder\ExportScheduler\Models\CustomReport;
use Visualbuilder\ExportScheduler\Models\ScheduledReport;
use Visualbuilder\ExportScheduler\Support\VisibilityBypass;
use Visualbuilder\ExportScheduler\Tests\Models\User;

use function Pest\Livewire\livewire;

function grantBypassTo(User ...$users): void
{
    $ids = array_map(fn (User $user) => (string) $user->getKey(), $users);

    app()->singleton(BypassesReportVisibility::class, fn () => new class($ids) implements BypassesReportVisibility
    {
        public function __construct(private array $ids) {}

        public function can(?Model $user): bool
        {
            return $user !== null && in_array((string) $user->getKey(), $this->ids, true);
        }
    });
}

it('grants nobody a bypass by default', function () {
    $user = User::factory()->create();

    expect(app(BypassesReportVisibility::class))->toBeInstanceOf(VisibilityBypass::class);

    expect(app(BypassesReportVisibility::class))
        ->can($user)->toBeFalse()
        ->can(null)->toBeFalse();
});

it('never grants a bypass to a guest', function () {
    grantBypassTo();

    expect(app(BypassesReportVisibility::class)->can(null))->toBeFalse();
});

it('makes an owner-only report visible to a bypassing user', function () {
    $privileged = User::factory()->create();
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $report = CustomReport::factory()->create([
        'owner_id' => $owner->id,
        'owner_type' => User::class,
        'visibility' => ReportVisibility::OWNER,
    ]);

    grantBypassTo($privileged);

    expect($report)
        ->isVisibleTo($other)->toBeFalse()
        ->isVisibleTo($owner)->toBeTrue()
        ->isVisibleTo($privileged)->toBeTrue();
});

it('makes a named-users report visible to a bypassing user who is not named', function () {
    $privileged = User::factory()->create();
    $owner = User::factory()->create();
    $named = User::factory()->create();
    $other = User::factory()->create();

    $report = CustomReport::factory()->create([
        'owner_id' => $owner->id,
        'owner_type' => User::class,
        'visibility' => ReportVisibility::NAMED_USERS,
        'visible_to_type' => User::class,
        'visible_to_ids' => [(string) $named->id],
    ]);

    grantBypassTo($privileged);

    expect($report)
        ->isVisibleTo($named)->toBeTrue()
        ->isVisibleTo($other)->toBeFalse()
        ->isVisibleTo($privileged)->toBeTrue();
});

it('returns every report from the visibleTo scope for a bypassing user', function () {
    $privileged = User::factory()->create();
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $owned = CustomReport::factory()->create([
        'owner_id' => $owner->id,
        'owner_type' => User::class,
        'visibility' => ReportVisibility::OWNER,
    ]);

    CustomReport::factory()->create([
        'owner_id' => $other->id,
        'owner_type' => User::class,
        'visibility' => ReportVisibility::OWNER,
    ]);

    grantBypassTo($privileged);

    expect(CustomReport::visibleTo($owner)->pluck('id')->all())->toEqual([$owned->id]);
    expect(CustomReport::visibleTo($privileged)->count())->toBe(2);
});

it('still excludes a guest from the visibleTo scope', function () {
    CustomReport::factory()->create();

    grantBypassTo();

    expect(CustomReport::visibleTo(null)->count())->toBe(0);
});

it('lets a bypassing user edit and delete a report they do not own', function () {
    $privileged = User::factory()->create();
    $owner = User::factory()->create();

    $report = CustomReport::factory()->create([
        'owner_id' => $owner->id,
        'owner_type' => User::class,
        'visibility' => ReportVisibility::OWNER,
    ]);

    grantBypassTo($privileged);
    $this->actingAs($privileged);

    expect(CustomReportResource::canEdit($report))->toBeTrue();
    expect(CustomReportResource::canDelete($report))->toBeTrue();
});

it('still refuses edit and delete to a non-bypassing non-owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $report = CustomReport::factory()->create([
        'owner_id' => $owner->id,
        'owner_type' => User::class,
        'visibility' => ReportVisibility::USER_TYPE,
        'visible_to_type' => User::class,
    ]);

    $this->actingAs($other);

    expect(CustomReportResource::canEdit($report))->toBeFalse();
    expect(CustomReportResource::canDelete($report))->toBeFalse();
});

it('shows the edit and delete row actions to a bypassing user', function () {
    $privileged = User::factory()->create();
    $owner = User::factory()->create();

    $report = CustomReport::factory()->create([
        'owner_id' => $owner->id,
        'owner_type' => User::class,
        'visibility' => ReportVisibility::USER_TYPE,
        'visible_to_type' => User::class,
    ]);

    $this->actingAs($privileged);
    livewire(ListCustomReports::class)
        ->assertActionHidden(TestAction::make('edit')->table($report))
        ->assertActionHidden(TestAction::make('delete')->table($report));

    grantBypassTo($privileged);
    livewire(ListCustomReports::class)
        ->assertActionVisible(TestAction::make('edit')->table($report))
        ->assertActionVisible(TestAction::make('delete')->table($report));
});

it('shows the schedules relation manager to a bypassing user', function () {
    $privileged = User::factory()->create();
    $owner = User::factory()->create();

    $report = CustomReport::factory()->create([
        'owner_id' => $owner->id,
        'owner_type' => User::class,
        'visibility' => ReportVisibility::USER_TYPE,
        'visible_to_type' => User::class,
    ]);

    $this->actingAs($privileged);
    expect(SchedulesRelationManager::canViewForRecord($report, ViewCustomReport::class))->toBeFalse();

    grantBypassTo($privileged);
    expect(SchedulesRelationManager::canViewForRecord($report, ViewCustomReport::class))->toBeTrue();
});

it('lists schedules on reports owned by others for a bypassing user', function () {
    $privileged = User::factory()->create();
    $owner = User::factory()->create();

    $ownReport = CustomReport::factory()->create([
        'owner_id' => $privileged->id,
        'owner_type' => User::class,
    ]);

    $otherReport = CustomReport::factory()->create([
        'owner_id' => $owner->id,
        'owner_type' => User::class,
    ]);

    ScheduledReport::factory()->create(['custom_report_id' => $ownReport->id]);
    ScheduledReport::factory()->create(['custom_report_id' => $otherReport->id]);

    $this->actingAs($privileged);
    expect(ScheduledReportResource::getEloquentQuery()->count())->toBe(1);

    grantBypassTo($privileged);
    expect(ScheduledReportResource::getEloquentQuery()->count())->toBe(2);
});

it('shows the run action to a bypassing user on a schedule they do not own', function () {
    $privileged = User::factory()->create();
    $owner = User::factory()->create();

    $report = CustomReport::factory()->create([
        'owner_id' => $owner->id,
        'owner_type' => User::class,
    ]);

    $schedule = ScheduledReport::factory()->create(['custom_report_id' => $report->id]);

    grantBypassTo($privileged);
    $this->actingAs($privileged);

    livewire(ListScheduledReports::class)
        ->assertActionVisible(TestAction::make('run')->table($schedule));
});

it('hides the run action from a non-bypassing non-owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $report = CustomReport::factory()->create([
        'owner_id' => $owner->id,
        'owner_type' => User::class,
    ]);

    $schedule = ScheduledReport::factory()->create(['custom_report_id' => $report->id]);

    $this->actingAs($other);

    expect(RunExport::make('run')->record($schedule)->isVisible())->toBeFalse();
});

it('lets a bypassing user edit and delete a schedule on a report they do not own', function () {
    $privileged = User::factory()->create();
    $owner = User::factory()->create();

    $report = CustomReport::factory()->create([
        'owner_id' => $owner->id,
        'owner_type' => User::class,
    ]);

    $schedule = ScheduledReport::factory()->create(['custom_report_id' => $report->id]);

    grantBypassTo($privileged);
    $this->actingAs($privileged);

    expect(ScheduledReportResource::canEdit($schedule))->toBeTrue();
    expect(ScheduledReportResource::canDelete($schedule))->toBeTrue();
});
