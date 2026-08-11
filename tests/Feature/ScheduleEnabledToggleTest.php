<?php

use Visualbuilder\ExportScheduler\Enums\ReportType;
use Visualbuilder\ExportScheduler\Enums\ScheduleFrequency;
use Visualbuilder\ExportScheduler\Filament\Exporters\UserExporter;
use Visualbuilder\ExportScheduler\Filament\Resources\ScheduledReportResource\Pages\CreateScheduledReport;
use Visualbuilder\ExportScheduler\Filament\Resources\ScheduledReportResource\Pages\EditScheduledReport;
use Visualbuilder\ExportScheduler\Models\CustomReport;
use Visualbuilder\ExportScheduler\Models\ScheduledReport;

use function Pest\Livewire\livewire;

/**
 * A schedule must be switchable on and off from the form itself, not only from the list,
 * and must default to on so it is never saved silently disabled. Both mounts compose
 * ScheduleFields::schema(), so covering the standalone pages covers the relation manager
 * modal too. Previously untested, and a field is easy to lose when a form is recomposed.
 */
function makeToggleReport(): CustomReport
{
    return CustomReport::create([
        'name' => 'Toggle Report',
        'report_type' => ReportType::EXPORTER,
        'exporter' => UserExporter::class,
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'formats' => ['csv'],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);
}

it('offers the enabled toggle on the create form, defaulting to on', function () {
    livewire(CreateScheduledReport::class)
        ->assertFormFieldExists('enabled')
        ->assertFormSet(['enabled' => true]);
});

it('creates an enabled schedule when the toggle is left alone', function () {
    $report = makeToggleReport();

    livewire(CreateScheduledReport::class)
        ->fillForm([
            'custom_report_id' => $report->getKey(),
            'schedule_frequency' => ScheduleFrequency::DAILY->value,
            'schedule_time' => '03:00',
            'recipient_type' => get_class(auth()->user()),
            'recipient_id' => auth()->id(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(ScheduledReport::latest('id')->first()->enabled)->toBeTrue();
});

it('creates a disabled schedule when the toggle is turned off', function () {
    $report = makeToggleReport();

    livewire(CreateScheduledReport::class)
        ->fillForm([
            'custom_report_id' => $report->getKey(),
            'schedule_frequency' => ScheduleFrequency::DAILY->value,
            'schedule_time' => '03:00',
            'recipient_type' => get_class(auth()->user()),
            'recipient_id' => auth()->id(),
            'enabled' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(ScheduledReport::latest('id')->first()->enabled)->toBeFalse();
});

it('reflects a disabled schedule on the edit form and can re-enable it', function () {
    $report = makeToggleReport();

    $schedule = ScheduledReport::create([
        'custom_report_id' => $report->getKey(),
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => '03:00',
        'recipient_type' => get_class(auth()->user()),
        'recipient_id' => auth()->id(),
        'enabled' => false,
    ]);

    livewire(EditScheduledReport::class, ['record' => $schedule->getKey()])
        ->assertFormSet(['enabled' => false])
        ->fillForm(['enabled' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($schedule->refresh()->enabled)->toBeTrue();
});
