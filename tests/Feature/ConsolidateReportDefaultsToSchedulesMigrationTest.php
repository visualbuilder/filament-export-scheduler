<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidates report defaults to schedules. Date range filtering is now exclusively
 * via Filter by Attributes, so date_range is dropped entirely. File format moves from
 * reports to schedules (one format per schedule).
 *
 * The test schema no longer has the report columns, so they are restored first.
 */
function consolidatedMigration(): object
{
    return require __DIR__ . '/../../database/migrations/2026_08_18_000001_consolidate_report_defaults_to_schedules.php';
}

function restoreReportColumns(): void
{
    Schema::table('custom_reports', function (Blueprint $table) {
        if (! Schema::hasColumn('custom_reports', 'date_range')) {
            $table->string('date_range')->nullable();
        }

        if (! Schema::hasColumn('custom_reports', 'formats')) {
            $table->json('formats')->nullable();
        }
    });
}

function restoreScheduleDateRangeColumn(): void
{
    Schema::table('scheduled_reports', function (Blueprint $table) {
        if (! Schema::hasColumn('scheduled_reports', 'date_range')) {
            $table->string('date_range')->nullable();
        }
    });
}

function insertReport(array $attributes = []): int
{
    return DB::table('custom_reports')->insertGetId(array_merge([
        'name' => 'Report',
        'report_type' => 'exporter',
        'visibility' => 'owner',
        'created_at' => now(),
        'updated_at' => now(),
    ], $attributes));
}

function insertSchedule(int $reportId, array $attributes = []): int
{
    return DB::table('scheduled_reports')->insertGetId(array_merge([
        'custom_report_id' => $reportId,
        'schedule_frequency' => 'daily',
        'schedule_time' => '09:00:00',
        'schedule_timezone' => 'UTC',
        'enabled' => true,
        'send_empty_report' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ], $attributes));
}

beforeEach(function () {
    restoreReportColumns();
    restoreScheduleDateRangeColumn();
});

it('drops date_range from both tables', function () {
    expect(Schema::hasColumn('custom_reports', 'date_range'))->toBeTrue();
    expect(Schema::hasColumn('scheduled_reports', 'date_range'))->toBeTrue();

    consolidatedMigration()->up();

    expect(Schema::hasColumn('custom_reports', 'date_range'))->toBeFalse();
    expect(Schema::hasColumn('scheduled_reports', 'date_range'))->toBeFalse();
});

it('drops formats from custom_reports', function () {
    expect(Schema::hasColumn('custom_reports', 'formats'))->toBeTrue();

    consolidatedMigration()->up();

    expect(Schema::hasColumn('custom_reports', 'formats'))->toBeFalse();
});

it('collapses multi-format schedules to first entry', function () {
    $report = insertReport();
    $schedule = insertSchedule($report, ['formats' => json_encode(['csv', 'xlsx'])]);

    consolidatedMigration()->up();

    expect(json_decode(DB::table('scheduled_reports')->find($schedule)->formats, true))->toBe(['csv']);
});

it('defaults to xlsx when schedule has no formats', function () {
    $report = insertReport();
    $schedule = insertSchedule($report, ['formats' => null]);

    consolidatedMigration()->up();

    expect(json_decode(DB::table('scheduled_reports')->find($schedule)->formats, true))->toBe(['xlsx']);
});

it('is idempotent when run twice', function () {
    $report = insertReport();
    $schedule = insertSchedule($report, ['formats' => json_encode(['csv', 'xlsx']), 'date_range' => 'today']);

    $migration = consolidatedMigration();
    $migration->up();

    $firstRun = DB::table('scheduled_reports')->find($schedule);
    $firstRunFormats = json_decode($firstRun->formats, true);

    // Run again to ensure idempotency
    $migration->up();

    $secondRun = DB::table('scheduled_reports')->find($schedule);
    $secondRunFormats = json_decode($secondRun->formats, true);

    expect($firstRunFormats)->toBe(['csv']);
    expect($secondRunFormats)->toBe(['csv']);
});

it('can be rolled back to restore schema structure', function () {
    $migration = consolidatedMigration();
    $migration->up();

    expect(Schema::hasColumn('custom_reports', 'date_range'))->toBeFalse();
    expect(Schema::hasColumn('custom_reports', 'formats'))->toBeFalse();
    expect(Schema::hasColumn('scheduled_reports', 'date_range'))->toBeFalse();

    $migration->down();

    expect(Schema::hasColumn('custom_reports', 'date_range'))->toBeTrue();
    expect(Schema::hasColumn('custom_reports', 'formats'))->toBeTrue();
    expect(Schema::hasColumn('scheduled_reports', 'date_range'))->toBeTrue();
});
