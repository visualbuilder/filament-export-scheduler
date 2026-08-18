<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The migration that pushes date range and file format down onto schedules before
 * dropping them from reports. Existing deliveries must not change, and every
 * schedule must come out with exactly one format.
 *
 * The test schema no longer has the report columns, so they are put back first to
 * recreate the pre-migration shape.
 */
function migrationUnderTest(): object
{
    return require __DIR__ . '/../../database/migrations/2026_08_18_000001_move_report_defaults_to_schedules.php';
}

function restoreReportDefaultColumns(): void
{
    Schema::table('custom_reports', function (Blueprint $table) {
        $table->string('date_range')->nullable();
        $table->json('formats')->nullable();
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
    restoreReportDefaultColumns();
});

it('drops the date range and formats columns from reports', function () {
    expect(Schema::hasColumn('custom_reports', 'date_range'))->toBeTrue();

    migrationUnderTest()->up();

    expect(Schema::hasColumn('custom_reports', 'date_range'))->toBeFalse()
        ->and(Schema::hasColumn('custom_reports', 'formats'))->toBeFalse();
});

it('pushes the report date range down onto a schedule that has none', function () {
    $report = insertReport(['date_range' => 'last_7_days']);
    $schedule = insertSchedule($report, ['date_range' => null]);

    migrationUnderTest()->up();

    expect(DB::table('scheduled_reports')->find($schedule)->date_range)->toBe('last_7_days');
});

it('leaves a schedule that already chose its own date range alone', function () {
    $report = insertReport(['date_range' => 'last_7_days']);
    $schedule = insertSchedule($report, ['date_range' => 'today']);

    migrationUnderTest()->up();

    expect(DB::table('scheduled_reports')->find($schedule)->date_range)->toBe('today');
});

it('inherits the report format when the schedule has none', function () {
    $report = insertReport(['formats' => json_encode(['csv'])]);
    $schedule = insertSchedule($report, ['formats' => null]);

    migrationUnderTest()->up();

    expect(json_decode(DB::table('scheduled_reports')->find($schedule)->formats, true))->toBe(['csv']);
});

it('collapses a schedule holding two formats down to the first', function () {
    $report = insertReport();
    $schedule = insertSchedule($report, ['formats' => json_encode(['csv', 'xlsx'])]);

    migrationUnderTest()->up();

    expect(json_decode(DB::table('scheduled_reports')->find($schedule)->formats, true))->toBe(['csv']);
});

it('defaults to xlsx when neither the schedule nor the report names a format', function () {
    $report = insertReport();
    $schedule = insertSchedule($report, ['formats' => null]);

    migrationUnderTest()->up();

    expect(json_decode(DB::table('scheduled_reports')->find($schedule)->formats, true))->toBe(['xlsx']);
});

it('can be run twice without changing the result', function () {
    $report = insertReport(['date_range' => 'today', 'formats' => json_encode(['csv'])]);
    $schedule = insertSchedule($report, ['formats' => null, 'date_range' => null]);

    $migration = migrationUnderTest();
    $migration->up();
    $migration->up();

    $row = DB::table('scheduled_reports')->find($schedule);

    expect($row->date_range)->toBe('today')
        ->and(json_decode($row->formats, true))->toBe(['csv']);
});
