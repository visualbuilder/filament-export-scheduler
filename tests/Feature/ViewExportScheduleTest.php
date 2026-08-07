<?php

use Visualbuilder\ExportScheduler\Enums\DateRange;
use Visualbuilder\ExportScheduler\Enums\ReportType;
use Visualbuilder\ExportScheduler\Enums\ScheduleFrequency;
use Visualbuilder\ExportScheduler\Filament\Exporters\UserExporter;
use Visualbuilder\ExportScheduler\Filament\Resources\ExportScheduleResource\Pages\ViewExportSchedule;
use Visualbuilder\ExportScheduler\Models\ExportSchedule;
use Visualbuilder\ExportScheduler\Tests\Models\User;

use function Pest\Livewire\livewire;

function makeSchedule(array $overrides = []): ExportSchedule
{
    return ExportSchedule::create(array_merge([
        'name' => 'Preview Report',
        'exporter' => UserExporter::class,
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
        ],
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => '08:00',
        'schedule_timezone' => 'UTC',
        'formats' => ['csv'],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'enabled' => true,
    ], $overrides));
}

it('lists the rows an exporter report would export', function () {
    $schedule = makeSchedule();

    $included = User::create([
        'name' => 'Included',
        'email' => 'included@domain.com',
        'password' => 'password',
    ]);

    livewire(ViewExportSchedule::class, ['record' => $schedule->getKey()])
        ->assertOk()
        ->assertCanSeeTableRecords([$included, auth()->user()])
        ->assertSee('included@domain.com');
});

it('only lists rows matching the schedule date range', function () {
    $schedule = makeSchedule(['date_range' => DateRange::TODAY]);

    $old = User::create([
        'name' => 'Old',
        'email' => 'old@domain.com',
        'password' => 'password',
        'created_at' => now()->subMonth(),
    ]);

    $recent = User::create([
        'name' => 'Recent',
        'email' => 'recent@domain.com',
        'password' => 'password',
    ]);

    livewire(ViewExportSchedule::class, ['record' => $schedule->getKey()])
        ->assertOk()
        ->assertCanSeeTableRecords([$recent])
        ->assertCanNotSeeTableRecords([$old]);
});

it('lists the rows of a sql query report', function () {
    $schedule = makeSchedule([
        'report_type' => ReportType::SQL_QUERY,
        'exporter' => null,
        'columns' => null,
        'sql_query' => 'SELECT id, email FROM users ORDER BY id',
    ]);

    User::create([
        'name' => 'From Sql',
        'email' => 'from-sql@domain.com',
        'password' => 'password',
    ]);

    livewire(ViewExportSchedule::class, ['record' => $schedule->getKey()])
        ->assertOk()
        ->assertSee('from-sql@domain.com')
        ->assertSee('Email');
});

it('shows an empty table when a sql query report is not a safe select', function () {
    $schedule = makeSchedule([
        'report_type' => ReportType::SQL_QUERY,
        'exporter' => null,
        'columns' => null,
        'sql_query' => 'DELETE FROM users',
    ]);

    livewire(ViewExportSchedule::class, ['record' => $schedule->getKey()])
        ->assertOk()
        ->assertSee('No rows to show');
});

it('does not run an export when previewing', function () {
    $schedule = makeSchedule();

    livewire(ViewExportSchedule::class, ['record' => $schedule->getKey()])
        ->assertOk();

    expect(\Filament\Actions\Exports\Models\Export::count())->toBe(0);
    expect($schedule->refresh()->last_run_at)->toBeNull();
});
it('links to the view page from the list table', function () {
    $schedule = makeSchedule();

    livewire(\Visualbuilder\ExportScheduler\Filament\Resources\ExportScheduleResource\Pages\ListExportSchedules::class)
        ->assertOk()
        ->assertTableActionExists('view')
        ->assertTableActionHasUrl('view', \Visualbuilder\ExportScheduler\Filament\Resources\ExportScheduleResource::getUrl('view', ['record' => $schedule]), record: $schedule);
});

it('paginates sql query rows', function () {
    $schedule = makeSchedule([
        'report_type' => ReportType::SQL_QUERY,
        'exporter' => null,
        'columns' => null,
        'sql_query' => 'SELECT id, email FROM users ORDER BY id',
    ]);

    foreach (range(1, 12) as $i) {
        User::create([
            'name' => "User {$i}",
            'email' => "user{$i}@domain.com",
            'password' => 'password',
        ]);
    }

    livewire(ViewExportSchedule::class, ['record' => $schedule->getKey()])
        ->set('tableRecordsPerPage', 5)
        ->assertSee('admin@domain.com')
        ->assertDontSee('user11@domain.com')
        ->call('setPage', 3)
        ->assertSee('user11@domain.com')
        ->assertDontSee('admin@domain.com');
});

it('exposes a view page url for a schedule', function () {
    $schedule = makeSchedule();

    expect(\Visualbuilder\ExportScheduler\Filament\Resources\ExportScheduleResource::hasPage('view'))->toBeTrue();
    expect(\Visualbuilder\ExportScheduler\Filament\Resources\ExportScheduleResource::getUrl('view', ['record' => $schedule]))
        ->toContain('/' . $schedule->getKey() . '/view');
});

