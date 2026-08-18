<?php

use Visualbuilder\ExportScheduler\Enums\ReportType;
use Visualbuilder\ExportScheduler\Enums\ReportVisibility;
use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource\Pages\CreateCustomReport;
use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource\Pages\EditCustomReport;
use Visualbuilder\ExportScheduler\Models\CustomReport;
use Visualbuilder\ExportScheduler\Tests\Models\User;

use function Pest\Livewire\livewire;

/**
 * The owner was always stored but never editable, so a report could only ever
 * belong to whoever happened to create it. It is now a field: pre-filled with the
 * creator, and transferable to somebody else.
 */
beforeEach(function () {
    // No role gate, so the report type picker offers SQL Query at all.
    config()->set('export-scheduler.sql_query_roles', []);
});

/**
 * A SQL query report, because the exporter picker only lists classes found under
 * the application's own app/ directory and so has no options in this package's
 * test suite. Ownership behaves identically either way.
 */
function reportFormData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ownership Report',
        'report_type' => ReportType::SQL_QUERY->value,
        'sql_query' => 'SELECT id, email FROM users',
        'visibility' => ReportVisibility::OWNER->value,
    ], $overrides);
}

it('pre-fills the owner with the user building the report', function () {
    $creator = User::factory()->create();

    $this->actingAs($creator);

    livewire(CreateCustomReport::class)
        ->assertSchemaStateSet([
            'owner_type' => User::class,
            'owner_id' => $creator->getKey(),
        ]);
});

it('keeps the creator as owner when the field is left alone', function () {
    $creator = User::factory()->create();

    $this->actingAs($creator);

    livewire(CreateCustomReport::class)
        ->fillForm(reportFormData())
        ->call('create')
        ->assertHasNoFormErrors();

    $report = CustomReport::latest('id')->first();

    expect($report->owner_type)->toBe(User::class)
        ->and((string) $report->owner_id)->toBe((string) $creator->getKey());
});

it('lets a report be created on somebody else behalf', function () {
    $creator = User::factory()->create();
    $recipient = User::factory()->create();

    $this->actingAs($creator);

    livewire(CreateCustomReport::class)
        ->fillForm(reportFormData([
            'owner_type' => User::class,
            'owner_id' => $recipient->getKey(),
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    $report = CustomReport::latest('id')->first();

    expect((string) $report->owner_id)->toBe((string) $recipient->getKey());

    // Ownership carries the rights with it, so the creator has handed them over.
    expect($report->isOwnedBy($recipient))->toBeTrue()
        ->and($report->isOwnedBy($creator))->toBeFalse();
});

it('lets an owner transfer a report they already own', function () {
    $owner = User::factory()->create();
    $successor = User::factory()->create();

    $report = CustomReport::factory()->create([
        'owner_id' => $owner->id,
        'owner_type' => User::class,
        'report_type' => ReportType::SQL_QUERY,
        'exporter' => null,
        'columns' => null,
        'sql_query' => 'SELECT id, email FROM users',
        'visibility' => ReportVisibility::OWNER,
    ]);

    $this->actingAs($owner);

    livewire(EditCustomReport::class, ['record' => $report->getKey()])
        // report_type is restated because the model casts it to an enum, which the
        // radio does not match back to its own string-keyed options.
        ->fillForm([
            'report_type' => ReportType::SQL_QUERY->value,
            'owner_id' => $successor->getKey(),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect((string) $report->refresh()->owner_id)->toBe((string) $successor->getKey());
});
