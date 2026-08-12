<?php

use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Testing\TestAction;
use Visualbuilder\ExportScheduler\Enums\DateRange;
use Visualbuilder\ExportScheduler\Enums\ReportType;
use Visualbuilder\ExportScheduler\Enums\ScheduleFrequency;
use Visualbuilder\ExportScheduler\Filament\Exporters\UserExporter;
use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource;
use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource\Pages\ListCustomReports;
use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource\Pages\ViewCustomReport;
use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource\RelationManagers\SchedulesRelationManager;
use Visualbuilder\ExportScheduler\Models\CustomReport;
use Visualbuilder\ExportScheduler\Models\ScheduledReport;
use Visualbuilder\ExportScheduler\Tests\Exporters\DocumentOwnerExporter;
use Visualbuilder\ExportScheduler\Tests\Models\Document;
use Visualbuilder\ExportScheduler\Tests\Models\User;

use function Pest\Livewire\livewire;

function makeSchedule(array $overrides = []): CustomReport
{
    return CustomReport::create(array_merge([
        'name' => 'Preview Report',
        'exporter' => UserExporter::class,
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
        ],
        'formats' => ['csv'],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ], $overrides));
}

it('lists the rows an exporter report would export', function () {
    $schedule = makeSchedule();

    $included = User::create([
        'name' => 'Included',
        'email' => 'included@domain.com',
        'password' => 'password',
    ]);

    livewire(ViewCustomReport::class, ['record' => $schedule->getKey()])
        ->assertOk()
        ->assertCanSeeTableRecords([$included, auth()->user()])
        ->assertSee('included@domain.com');
});

it('only lists rows matching the schedule date range', function () {
    $schedule = makeSchedule(['date_range' => DateRange::TODAY], );

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

    livewire(ViewCustomReport::class, ['record' => $schedule->getKey()])
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

    livewire(ViewCustomReport::class, ['record' => $schedule->getKey()])
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

    livewire(ViewCustomReport::class, ['record' => $schedule->getKey()])
        ->assertOk()
        ->assertSee('No rows to show');
});

it('does not run an export when previewing', function () {
    $schedule = makeSchedule();

    livewire(ViewCustomReport::class, ['record' => $schedule->getKey()])
        ->assertOk();

    expect(Export::count())->toBe(0);
});
it('links to the view page from the list table', function () {
    $schedule = makeSchedule();

    livewire(ListCustomReports::class)
        ->assertOk()
        ->assertActionExists(TestAction::make('view')->table())
        ->assertActionHasUrl(TestAction::make('view')->table($schedule), CustomReportResource::getUrl('view', ['record' => $schedule]));
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

    livewire(ViewCustomReport::class, ['record' => $schedule->getKey()])
        ->set('tableRecordsPerPage', 5)
        ->assertSee('admin@domain.com')
        ->assertDontSee('user11@domain.com')
        ->call('setPage', 3)
        ->assertSee('user11@domain.com')
        ->assertDontSee('admin@domain.com');
});

it('exposes a view page url for a schedule', function () {
    $schedule = makeSchedule();

    expect(CustomReportResource::hasPage('view'))->toBeTrue();
    expect(CustomReportResource::getUrl('view', ['record' => $schedule]))
        ->toContain('/' . $schedule->getKey() . '/view');
});

it('shows fifty rows per page by default', function () {
    $schedule = makeSchedule();

    livewire(ViewCustomReport::class, ['record' => $schedule->getKey()])
        ->assertOk()
        ->assertSet('tableRecordsPerPage', 50);
});

it('searches every field across all pages of an exporter report', function () {
    $schedule = makeSchedule();

    foreach (range(1, 60) as $i) {
        User::create([
            'name' => "User {$i}",
            'email' => "user{$i}@domain.com",
            'password' => 'password',
        ]);
    }

    $needle = User::create([
        'name' => 'Needle',
        'email' => 'needle@elsewhere.test',
        'password' => 'password',
    ]);

    // The match is on the last page, so it can only be found by searching the whole result set.
    livewire(ViewCustomReport::class, ['record' => $schedule->getKey()])
        ->assertDontSee('needle@elsewhere.test')
        ->searchTable('needle@elsewhere')
        ->assertSee('needle@elsewhere.test')
        ->assertDontSee('user1@domain.com')
        ->assertCanSeeTableRecords([$needle]);
});

it('shows the empty state when a search matches nothing', function () {
    $schedule = makeSchedule();

    livewire(ViewCustomReport::class, ['record' => $schedule->getKey()])
        ->searchTable('nothing-matches-this')
        ->assertSee('No rows to show');
});

it('searches the value produced by the exporter formatting', function () {
    $schedule = makeSchedule([
        'columns' => [
            ['name' => 'email', 'label' => 'Email'],
            ['name' => 'created_at', 'label' => 'Date Added'],
        ],
    ]);

    User::create([
        'name' => 'Formatted',
        'email' => 'formatted@domain.com',
        'password' => 'password',
        'created_at' => now()->setDate(2024, 3, 17)->setTime(9, 30),
    ]);

    // 2024-03-17 is only searchable if the datetime cast has been rendered to a string.
    livewire(ViewCustomReport::class, ['record' => $schedule->getKey()])
        ->searchTable('2024-03-17')
        ->assertSee('formatted@domain.com')
        ->assertDontSee('admin@domain.com');
});

it('sorts an exporter report by a column header', function () {
    $schedule = makeSchedule();

    User::create(['name' => 'Zeta', 'email' => 'zeta@domain.com', 'password' => 'password']);
    User::create(['name' => 'Alpha', 'email' => 'alpha@domain.com', 'password' => 'password']);

    livewire(ViewCustomReport::class, ['record' => $schedule->getKey()])
        ->sortTable('email')
        ->assertSeeInOrder(['admin@domain.com', 'alpha@domain.com', 'zeta@domain.com'])
        ->sortTable('email', 'desc')
        ->assertSeeInOrder(['zeta@domain.com', 'alpha@domain.com', 'admin@domain.com']);
});

it('searches and sorts a sql query report', function () {
    $schedule = makeSchedule([
        'report_type' => ReportType::SQL_QUERY,
        'exporter' => null,
        'columns' => null,
        'sql_query' => 'SELECT id, email FROM users',
    ]);

    User::create(['name' => 'Zeta', 'email' => 'zeta@domain.com', 'password' => 'password']);
    User::create(['name' => 'Alpha', 'email' => 'alpha@domain.com', 'password' => 'password']);

    livewire(ViewCustomReport::class, ['record' => $schedule->getKey()])
        ->sortTable('email', 'desc')
        ->assertSeeInOrder(['zeta@domain.com', 'alpha@domain.com', 'admin@domain.com'])
        ->searchTable('alpha')
        ->assertSee('alpha@domain.com')
        ->assertDontSee('zeta@domain.com');
});

it('ignores a sort column that is not part of the report', function () {
    $schedule = makeSchedule([
        'report_type' => ReportType::SQL_QUERY,
        'exporter' => null,
        'columns' => null,
        'sql_query' => 'SELECT id, email FROM users',
    ]);

    livewire(ViewCustomReport::class, ['record' => $schedule->getKey()])
        ->call('sortTable', 'not_a_column')
        ->assertOk()
        ->assertSee('admin@domain.com');
});

it('sorts and searches a morph relation column that a database sort could not handle', function () {
    $user = User::create(['name' => 'Owner', 'email' => 'owner@domain.com', 'password' => 'password']);

    Document::create(['title' => 'Zeta doc', 'owner_id' => $user->id, 'owner_type' => User::class]);
    Document::create(['title' => 'Alpha doc', 'owner_id' => $user->id, 'owner_type' => User::class]);

    $schedule = makeSchedule([
        'exporter' => DocumentOwnerExporter::class,
        'columns' => [
            ['name' => 'title', 'label' => 'Title'],
            ['name' => 'owner.created_at', 'label' => 'Owner Created At'],
        ],
    ]);

    livewire(ViewCustomReport::class, ['record' => $schedule->getKey()])
        ->assertOk()
        ->sortTable('title')
        ->assertSeeInOrder(['Alpha doc', 'Zeta doc'])
        ->sortTable('owner.created_at')
        ->assertOk()
        ->searchTable('Zeta')
        ->assertSee('Zeta doc')
        ->assertDontSee('Alpha doc');
});

it('renders the value of a relation column whose name contains a dot', function () {
    $owner = User::create([
        'name' => 'Owner',
        'email' => 'owner@domain.com',
        'password' => 'password',
        'created_at' => now()->setDate(2023, 5, 9)->setTime(1, 2),
    ]);

    Document::create(['title' => 'Doc A', 'owner_id' => $owner->id, 'owner_type' => User::class]);

    $schedule = makeSchedule([
        'exporter' => DocumentOwnerExporter::class,
        'columns' => [
            ['name' => 'title', 'label' => 'Title'],
            ['name' => 'owner.created_at', 'label' => 'Owner Created At'],
        ],
    ]);

    livewire(ViewCustomReport::class, ['record' => $schedule->getKey()])
        ->assertSee('Doc A')
        ->assertSee('2023-05-09');
});

it('shows an unconventional sql result set exactly as the query returns it', function () {
    User::create(['name' => 'Alpha', 'email' => 'alpha@domain.com', 'password' => 'password']);

    // A grouped report with a grand total appended as the last row.
    $schedule = makeSchedule([
        'report_type' => ReportType::SQL_QUERY,
        'exporter' => null,
        'columns' => null,
        'sql_query' => 'SELECT name AS "Name", COUNT(*) AS total FROM users GROUP BY name'
            . " UNION ALL SELECT 'Total', COUNT(*) FROM users",
    ]);

    livewire(ViewCustomReport::class, ['record' => $schedule->getKey()])
        ->assertOk()
        // The alias is shown verbatim, while a plain snake_case name is tidied up.
        ->assertSee('Name')
        ->assertSee('Total')
        // The total row stays where the query put it, at the end.
        ->assertSeeInOrder(['Admin', 'Alpha', 'Total']);
});

it('still shows the columns of a sql query report that matches no rows', function () {
    $schedule = makeSchedule([
        'report_type' => ReportType::SQL_QUERY,
        'exporter' => null,
        'columns' => null,
        'sql_query' => "SELECT id, email AS \"Contact Email\" FROM users WHERE email = 'nobody@nowhere.test'",
    ]);

    // An empty result set must still be recognisable as this report, not a blank page.
    livewire(ViewCustomReport::class, ['record' => $schedule->getKey()])
        ->assertOk()
        ->assertSee('Contact Email')
        ->assertSee('No rows to show');
});

it('offers the run action on a sql query report that has no exporter class', function () {
    $report = makeSchedule([
        'report_type' => ReportType::SQL_QUERY,
        'exporter' => null,
        'columns' => null,
        'sql_query' => 'SELECT id, email FROM users',
    ]);

    $schedule = ScheduledReport::create([
        'custom_report_id' => $report->id,
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => '08:00',
        'schedule_timezone' => 'UTC',
        'enabled' => true,
    ]);

    // willLogoutUser() used to ask a non-existent exporter class for its queue.
    expect($report->isSyncQueue())->toBeBool();
    expect($schedule->willLogoutUser())->toBeBool();

    livewire(SchedulesRelationManager::class, ['ownerRecord' => $report, 'pageClass' => ViewCustomReport::class])
        ->callAction(TestAction::make('run')->table($schedule))
        ->assertHasNoFormErrors();
});

it('treats a schedule with no owner as not owned by the current user', function () {
    $schedule = makeSchedule(['owner_id' => null, 'owner_type' => null]);

    expect($schedule->isOwnedBy(auth()->user()))->toBeFalse();
});

it('caps the rows loaded when a viewer limit is configured', function () {
    config()->set('export-scheduler.viewer_max_rows', 3);

    foreach (range(1, 10) as $i) {
        User::create([
            'name' => "User {$i}",
            'email' => "user{$i}@domain.com",
            'password' => 'password',
        ]);
    }

    livewire(ViewCustomReport::class, ['record' => makeSchedule()->getKey()])
        ->assertOk()
        ->assertSee('Showing the first 3 rows')
        ->assertDontSee('user9@domain.com');
});
