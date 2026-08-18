<?php

use Carbon\Carbon;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;
use Visualbuilder\ExportScheduler\Enums\DateRange;
use Visualbuilder\ExportScheduler\Filament\Exporters\UserExporter;
use Visualbuilder\ExportScheduler\Models\CustomReport;
use Visualbuilder\ExportScheduler\Models\ScheduledReport;
use Visualbuilder\ExportScheduler\Services\ScheduledExporter;
use Visualbuilder\ExportScheduler\Tests\Models\User;

beforeEach(function () {
    Notification::fake();
});

function fakeUserData(array $overrides = []): array
{
    return array_merge([
        'name' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
        'created_at' => now(),
    ], $overrides);
}

function createFakeUsers(int $count = 1, array $overrides = []): array | null | User
{
    $users = [];

    for ($i = 0; $i < $count; $i++) {
        $users[] = User::create(fakeUserData($overrides));
    }

    return $count === 1 ? Arr::first($users) : $users;
}

it('applies date range filter to export query', function () {
    Carbon::setTestNow('2024-06-15 12:00:00');

    // Create users with different dates
    createFakeUsers(overrides: ['created_at' => '2024-01-01']);
    createFakeUsers(overrides: ['created_at' => '2024-06-10']);

    $report = CustomReport::create([
        'name' => 'User Export with Date Range',
        'exporter' => UserExporter::class,
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);

    // The range belongs to the schedule: a report no longer carries one. The
    // recipient is the existing auth user because the factory would otherwise
    // create a fresh one, and this report exports users.
    $schedule = ScheduledReport::factory()->create([
        'custom_report_id' => $report->id,
        'date_range' => DateRange::LAST_7_DAYS,
        'formats' => [ExportFormat::Csv->value],
        'recipient_id' => auth()->id(),
        'recipient_type' => get_class(auth()->user()),
    ]);

    $exporter = new ScheduledExporter($report, $schedule);
    $exporter->run();

    // Only the recent user should be included (within last 7 days)
    expect($exporter->getTotalRows())->toBe(1);
});

it('exports every row when run ad hoc, since only a schedule carries a date range', function () {
    Carbon::setTestNow('2024-06-15 12:00:00');

    createFakeUsers(overrides: ['created_at' => '2024-01-01']);
    createFakeUsers(overrides: ['created_at' => '2024-06-10']);

    $report = CustomReport::create([
        'name' => 'User Export without Schedule',
        'exporter' => UserExporter::class,
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);

    $exporter = new ScheduledExporter($report);
    $exporter->run();

    // Both seeded users plus the signed-in one: no range means no cut-off.
    expect($exporter->getTotalRows())->toBe(3);
});

it('applies attribute filter with like operator', function () {
    $users = createFakeUsers(2);

    $report = CustomReport::create([
        'name' => 'User Export with Like Filter',
        'exporter' => UserExporter::class,
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'filters' => [
            'attributes' => [
                [
                    'column' => 'name',
                    'value' => $users[0]->name,
                    'operator' => 'like',
                    'condition' => 'and',
                ],
            ],
        ],
    ]);

    $exporter = new ScheduledExporter($report);
    $exporter->run();

    expect($exporter->getTotalRows())->toBe(1);
});

it('applies attribute filter with in operator', function () {
    $users = createFakeUsers(3);

    $report = CustomReport::create([
        'name' => 'User Export with In Filter',
        'exporter' => UserExporter::class,
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'filters' => [
            'attributes' => [
                [
                    'column' => 'id',
                    'value' => [$users[0]->id, $users[1]->id],
                    'operator' => 'in',
                    'condition' => 'and',
                ],
            ],
        ],
    ]);

    $exporter = new ScheduledExporter($report);
    $exporter->run();

    expect($exporter->getTotalRows())->toBe(2);
});

it('applies attribute filter with not_in operator', function () {
    $users = createFakeUsers(3);

    $report = CustomReport::create([
        'name' => 'User Export with Not In Filter',
        'exporter' => UserExporter::class,
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'filters' => [
            'attributes' => [
                [
                    'column' => 'id',
                    'value' => [$users[0]->id],
                    'operator' => 'not_in',
                    'condition' => 'and',
                ],
            ],
        ],
    ]);

    $exporter = new ScheduledExporter($report);
    $exporter->run();

    // Should exclude user1, so only user2, user3, and auth user
    expect($exporter->getTotalRows())->toBe(3);
});

it('applies attribute filter with since operator using array value', function () {
    Carbon::setTestNow('2024-06-15 12:00:00');

    createFakeUsers(3, ['created_at' => '2024-06-10']); // recent users
    createFakeUsers(3, ['created_at' => '2024-01-01']); // past users

    $report = CustomReport::create([
        'name' => 'User Export with Since Filter',
        'exporter' => UserExporter::class,
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'filters' => [
            'attributes' => [
                [
                    'column' => 'created_at',
                    'value' => ['amount' => 10, 'unit' => 'days'],
                    'operator' => 'since',
                    'condition' => 'and',
                ],
            ],
        ],
    ]);

    $exporter = new ScheduledExporter($report);
    $exporter->run();

    // Only users created in the last 10 days + auth user (auth user is also recent)
    expect($exporter->getTotalRows())->toBe(4);
});

it('applies attribute filter with date range operator', function () {
    Carbon::setTestNow('2024-06-15 12:00:00');

    // within range
    createFakeUsers(3, ['created_at' => '2024-06-14']); // 1 day ago
    createFakeUsers(3, ['created_at' => '2024-06-12']); // 3 days ago
    createFakeUsers(3, ['created_at' => '2024-06-10']); // 5 days ago
    createFakeUsers(3, ['created_at' => '2024-06-08']); // 7 days ago

    // outside range
    createFakeUsers(3);                                 // now
    createFakeUsers(3, ['created_at' => '2024-06-07']); // 8 days ago
    createFakeUsers(3, ['created_at' => '2024-06-05']); // 10 days ago

    $report = CustomReport::create([
        'name' => 'User Export with DateRange Filter',
        'exporter' => UserExporter::class,
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'filters' => [
            'attributes' => [
                [
                    'column' => 'created_at',
                    'value' => DateRange::LAST_7_DAYS->value,
                    'operator' => '<>',
                    'condition' => 'and',
                ],
            ],
        ],
    ]);

    $exporter = new ScheduledExporter($report);
    $exporter->run();

    expect($exporter->getTotalRows())->toBe(12);
});

it('applies multiple attribute filters with AND condition', function () {
    $similarName = 'similarname';
    $similarEmail = 'similaremail';
    $similarCount = 5;

    // similar users
    for ($i = 0; $i < $similarCount; $i++) {
        createFakeUsers(overrides: [
            'name' => $similarName . fake()->name(),
            'email' => $similarEmail . fake()->unique()->safeEmail(),
        ]);
    }

    // unique users
    createFakeUsers(3);

    $report = CustomReport::create([
        'name' => 'User Export with Multiple AND Filters',
        'exporter' => UserExporter::class,
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'filters' => [
            'attributes' => [
                [
                    'column' => 'name',
                    'value' => $similarName,
                    'operator' => 'like',
                    'condition' => 'and',
                ],
                [
                    'column' => 'email',
                    'value' => $similarEmail,
                    'operator' => 'like',
                    'condition' => 'and',
                ],
            ],
        ],
    ]);

    $exporter = new ScheduledExporter($report);
    $exporter->run();

    expect($exporter->getTotalRows())->toBe($similarCount);
});

it('applies attribute filters with OR condition', function () {
    $user1 = fakeUserData();
    $user2 = fakeUserData();

    createFakeUsers(overrides: $user1);
    createFakeUsers(overrides: $user2);
    createFakeUsers();

    $report = CustomReport::create([
        'name' => 'User Export with OR Filters',
        'exporter' => UserExporter::class,
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'filters' => [
            'attributes' => [
                [
                    'column' => 'name',
                    'value' => $user1['name'],
                    'operator' => 'like',
                    'condition' => 'and',
                ],
                [
                    'column' => 'name',
                    'value' => $user2['name'],
                    'operator' => 'like',
                    'condition' => 'or',
                ],
            ],
        ],
    ]);

    $exporter = new ScheduledExporter($report);
    $exporter->run();

    // John Doe, Jane Smith, and auth user
    expect($exporter->getTotalRows())->toBe(2);
});

it('skips filters with blank column or value', function () {
    createFakeUsers(10);

    $report = CustomReport::create([
        'name' => 'User Export with Blank Filters',
        'exporter' => UserExporter::class,
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'filters' => [
            'attributes' => [
                [
                    'column' => 'name',
                    'value' => '',
                    'operator' => 'like',
                    'condition' => 'and',
                ],
                [
                    'column' => '',
                    'value' => 'test',
                    'operator' => 'like',
                    'condition' => 'and',
                ],
            ],
        ],
    ]);

    $exporter = new ScheduledExporter($report);
    $exporter->run();

    // Should include all users since filters are blank
    expect($exporter->getTotalRows())->toBe(11); // + auth user
});

it('generates unique file names with timestamp', function () {
    Carbon::setTestNow('2024-06-15 14:30:00');

    $report = CustomReport::create([
        'name' => 'Test Export Schedule',
        'exporter' => UserExporter::class,
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);

    $exporter = new ScheduledExporter($report);
    $exporter->run();

    expect(Export::latest()->first()->file_name)->toContain('test-export-schedule', '2024-06-15', '1430');
});
