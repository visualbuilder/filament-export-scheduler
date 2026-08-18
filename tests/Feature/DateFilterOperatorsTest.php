<?php

use Carbon\Carbon;
use Filament\Actions\Exports\Enums\ExportFormat;
use Illuminate\Support\Facades\Notification;
use Visualbuilder\ExportScheduler\Filament\Exporters\UserExporter;
use Visualbuilder\ExportScheduler\Models\CustomReport;
use Visualbuilder\ExportScheduler\Services\ScheduledExporter;
use Visualbuilder\ExportScheduler\Tests\Models\User;

beforeEach(function () {
    Notification::fake();
    Carbon::setTestNow('2024-06-15 12:00:00');
});

function createDateFilterUser(array $overrides = []): User
{
    return User::create(array_merge([
        'name' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
        'created_at' => now(),
    ], $overrides));
}

function createScheduleWithAttributeFilter(array $filter): CustomReport
{
    return CustomReport::create([
        'name' => 'Date Filter Test',
        'exporter' => UserExporter::class,
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'formats' => [ExportFormat::Csv],
        'filters' => [
            'attributes' => [$filter],
        ],
    ]);
}

it('filters with "before" operator for future relative dates', function () {
    // User created 5 days from now (future)
    createDateFilterUser(['created_at' => '2024-06-20 12:00:00']);
    // User created 40 days from now (too far in future)
    createDateFilterUser(['created_at' => '2024-07-25 12:00:00']);
    // User created in the past
    createDateFilterUser(['created_at' => '2024-01-01 12:00:00']);

    $report = createScheduleWithAttributeFilter([
        'column' => 'created_at',
        'operator' => 'before',
        'value' => ['amount' => 30, 'unit' => 'days'],
        'condition' => 'and',
    ]);

    $exporter = new ScheduledExporter($report);
    $exporter->run();

    // "before" means <= now + 30 days (2024-07-15), so past user + 5-day-future user match, but not the 40-day one
    expect($exporter->getTotalRows())->toBe(2);
});

it('filters with "is_in" operator for future preset ranges', function () {
    // User created 3 days from now (within next 7 days)
    createDateFilterUser(['created_at' => '2024-06-18 12:00:00']);
    // User created 10 days from now (outside next 7 days)
    createDateFilterUser(['created_at' => '2024-06-25 12:00:00']);
    // User created in the past
    createDateFilterUser(['created_at' => '2024-01-01 12:00:00']);

    $report = createScheduleWithAttributeFilter([
        'column' => 'created_at',
        'operator' => 'is_in',
        'value' => 'next_7_days',
        'condition' => 'and',
    ]);

    $exporter = new ScheduledExporter($report);
    $exporter->run();

    // next_7_days = 2024-06-15 00:00:00 to 2024-06-21 23:59:59
    expect($exporter->getTotalRows())->toBe(1);
});

it('filters with "is_before" operator using absolute date', function () {
    createDateFilterUser(['created_at' => '2024-03-01 12:00:00']);
    createDateFilterUser(['created_at' => '2024-06-10 12:00:00']);

    $report = createScheduleWithAttributeFilter([
        'column' => 'created_at',
        'operator' => 'is_before',
        'value' => '2024-06-01',
        'condition' => 'and',
    ]);

    $exporter = new ScheduledExporter($report);
    $exporter->run();

    expect($exporter->getTotalRows())->toBe(1);
});

it('filters with "is_after" operator using absolute date', function () {
    createDateFilterUser(['created_at' => '2024-03-01 12:00:00']);
    createDateFilterUser(['created_at' => '2024-06-10 12:00:00']);

    $report = createScheduleWithAttributeFilter([
        'column' => 'created_at',
        'operator' => 'is_after',
        'value' => '2024-06-01',
        'condition' => 'and',
    ]);

    $exporter = new ScheduledExporter($report);
    $exporter->run();

    // The auth user (created at "now" = 2024-06-15) + the June 10 user are both after June 1
    expect($exporter->getTotalRows())->toBe(2);
});

it('combines past and future date filters', function () {
    // Past user
    createDateFilterUser(['created_at' => '2024-06-10 12:00:00']);
    // Future user
    createDateFilterUser(['created_at' => '2024-06-20 12:00:00']);
    // Very old user
    createDateFilterUser(['created_at' => '2023-01-01 12:00:00']);

    $report = CustomReport::create([
        'name' => 'Combined Date Filter Test',
        'exporter' => UserExporter::class,
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'formats' => [ExportFormat::Csv],
        'filters' => [
            'attributes' => [
                [
                    'column' => 'created_at',
                    'operator' => 'is_after',
                    'value' => '2024-06-01',
                    'condition' => 'and',
                ],
                [
                    'column' => 'created_at',
                    'operator' => 'is_before',
                    'value' => '2024-06-25',
                    'condition' => 'and',
                ],
            ],
        ],
    ]);

    $exporter = new ScheduledExporter($report);
    $exporter->run();

    // Both June 10 and June 20 are after June 1 AND before June 25
    expect($exporter->getTotalRows())->toBe(2);
});
