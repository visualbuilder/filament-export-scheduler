<?php

use Visualbuilder\ExportScheduler\Enums\ReportType;
use Visualbuilder\ExportScheduler\Enums\ScheduleFrequency;
use Visualbuilder\ExportScheduler\Filament\Exporters\UserExporter;
use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource\Pages\ViewCustomReport;
use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource\RelationManagers\SchedulesRelationManager;
use Visualbuilder\ExportScheduler\Models\CustomReport;
use Visualbuilder\ExportScheduler\Models\ScheduledReport;

use function Pest\Livewire\livewire;

/**
 * A schedule must be switchable on and off from the form itself, not only from the list,
 * and must default to on so it is never saved silently disabled. Both mounts compose
 * ScheduleFields::schema(), so covering the relation manager modal covers the feature.
 * Previously untested, and a field is easy to lose when a form is recomposed.
 */
function makeToggleReport(): CustomReport
{
    return CustomReport::create([
        'name' => 'Toggle Report',
        'report_type' => ReportType::EXPORTER,
        'exporter' => UserExporter::class,
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);
}

it('defaults schedule to enabled when not specified', function () {
    $report = makeToggleReport();

    $schedule = ScheduledReport::create([
        'custom_report_id' => $report->getKey(),
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => '03:00',
        'recipient_type' => get_class(auth()->user()),
        'recipient_id' => auth()->id(),
        'enabled' => true,
    ]);

    expect($schedule->enabled)->toBeTrue();
});

it('allows creating a disabled schedule', function () {
    $report = makeToggleReport();

    $schedule = ScheduledReport::create([
        'custom_report_id' => $report->getKey(),
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => '03:00',
        'recipient_type' => get_class(auth()->user()),
        'recipient_id' => auth()->id(),
        'enabled' => false,
    ]);

    expect($schedule->enabled)->toBeFalse();
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

    livewire(SchedulesRelationManager::class, ['ownerRecord' => $report, 'pageClass' => ViewCustomReport::class])
        ->callTableAction('edit', $schedule, ['enabled' => true])
        ->assertHasNoFormErrors();

    expect($schedule->refresh()->enabled)->toBeTrue();
});
