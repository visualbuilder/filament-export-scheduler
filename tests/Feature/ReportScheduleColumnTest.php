<?php

use Visualbuilder\ExportScheduler\Enums\DayOfWeek;
use Visualbuilder\ExportScheduler\Enums\ScheduleFrequency;
use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource\Pages\ListCustomReports;
use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource\Pages\ViewCustomReport;
use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource\RelationManagers\SchedulesRelationManager;
use Visualbuilder\ExportScheduler\Models\CustomReport;
use Visualbuilder\ExportScheduler\Models\ScheduledReport;

use function Pest\Livewire\livewire;

/**
 * The reports list only showed a count of schedules, so finding which report
 * fires at a given time meant opening every report in turn. The list now spells
 * each schedule out, marking disabled ones, and the schedules tab shows the same
 * wording beside the frequency badge.
 */
function scheduledReportForColumn(): CustomReport
{
    return CustomReport::factory()->create([
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);
}

it('lists every schedule in words on the reports table', function () {
    $report = scheduledReportForColumn();

    ScheduledReport::factory()->create([
        'custom_report_id' => $report->id,
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => '19:00:00',
        'schedule_timezone' => config('app.timezone'),
    ]);

    ScheduledReport::factory()->create([
        'custom_report_id' => $report->id,
        'schedule_frequency' => ScheduleFrequency::WEEKLY,
        'schedule_day_of_week' => DayOfWeek::MONDAY,
        'schedule_time' => '09:00:00',
        'schedule_timezone' => config('app.timezone'),
        'enabled' => false,
    ]);

    livewire(ListCustomReports::class)
        ->assertCanSeeTableRecords([$report])
        ->assertTableColumnExists('schedule_summary')
        ->assertSee('Daily at 19:00')
        ->assertSee('Weekly on Monday at 09:00 (disabled)');
});

it('shows a placeholder for a report with no schedules', function () {
    $report = scheduledReportForColumn();

    livewire(ListCustomReports::class)
        ->assertCanSeeTableRecords([$report])
        ->assertSee('Not scheduled');
});

it('shows the schedule in words on the schedules tab', function () {
    $report = scheduledReportForColumn();

    $schedule = ScheduledReport::factory()->create([
        'custom_report_id' => $report->id,
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => '19:00:00',
        'schedule_timezone' => config('app.timezone'),
    ]);

    livewire(SchedulesRelationManager::class, [
        'ownerRecord' => $report,
        'pageClass' => ViewCustomReport::class,
    ])
        ->assertCanSeeTableRecords([$schedule])
        ->assertTableColumnExists('schedule_summary')
        ->assertSee('Daily at 19:00');
});
