<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function removeDateRangeMigration(): object
{
    return require __DIR__ . '/../../database/migrations/2026_08_18_000002_remove_date_range_from_scheduled_reports.php';
}

function restoreScheduleDateRangeColumn(): void
{
    Schema::table('scheduled_reports', function (Blueprint $table) {
        if (! Schema::hasColumn('scheduled_reports', 'date_range')) {
            $table->string('date_range')->nullable();
        }
    });
}

beforeEach(function () {
    restoreScheduleDateRangeColumn();
});

it('drops the date_range column from scheduled_reports', function () {
    expect(Schema::hasColumn('scheduled_reports', 'date_range'))->toBeTrue();

    removeDateRangeMigration()->up();

    expect(Schema::hasColumn('scheduled_reports', 'date_range'))->toBeFalse();
});

it('restores the column as nullable on rollback', function () {
    $migration = removeDateRangeMigration();
    $migration->up();

    expect(Schema::hasColumn('scheduled_reports', 'date_range'))->toBeFalse();

    $migration->down();

    expect(Schema::hasColumn('scheduled_reports', 'date_range'))->toBeTrue();
});
