<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidate report defaults to schedules. Date range filtering is now exclusively
 * via the Filter by Attributes feature, so date_range is dropped entirely. File format
 * moves from reports to schedules (one format per schedule).
 *
 * End state: custom_reports has neither date_range nor formats; scheduled_reports
 * has only formats (collapsed to single entry).
 *
 * This is idempotent: it detects which columns exist and skips already-completed steps.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('scheduled_reports') || ! Schema::hasTable('custom_reports')) {
            return;
        }

        // Collapse schedule formats to single entry (if formats exist)
        if (Schema::hasColumn('scheduled_reports', 'formats')) {
            $this->collapseScheduleFormats();
        }

        // Drop all date_range columns (obsolete, now via Filter by Attributes)
        Schema::table('custom_reports', function (Blueprint $table) {
            if (Schema::hasColumn('custom_reports', 'date_range')) {
                $table->dropColumn('date_range');
            }
        });

        Schema::table('scheduled_reports', function (Blueprint $table) {
            if (Schema::hasColumn('scheduled_reports', 'date_range')) {
                $table->dropColumn('date_range');
            }
        });

        // Drop formats from custom_reports (now schedule-only)
        Schema::table('custom_reports', function (Blueprint $table) {
            if (Schema::hasColumn('custom_reports', 'formats')) {
                $table->dropColumn('formats');
            }
        });
    }

    /**
     * Rollback only restores schema structure (not data).
     */
    public function down(): void
    {
        if (! Schema::hasTable('custom_reports') || ! Schema::hasTable('scheduled_reports')) {
            return;
        }

        Schema::table('custom_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('custom_reports', 'date_range')) {
                $table->string('date_range')->nullable();
            }

            if (! Schema::hasColumn('custom_reports', 'formats')) {
                $table->json('formats')->nullable();
            }
        });

        Schema::table('scheduled_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('scheduled_reports', 'date_range')) {
                $table->string('date_range')->nullable();
            }
        });
    }

    /**
     * One file per schedule from here on. Anything empty becomes xlsx.
     */
    protected function collapseScheduleFormats(): void
    {
        DB::table('scheduled_reports')
            ->select('id', 'formats')
            ->orderBy('id')
            ->chunkById(200, function ($schedules) {
                foreach ($schedules as $schedule) {
                    $decoded = json_decode((string) $schedule->formats, true);
                    $first = is_array($decoded) ? collect($decoded)->filter()->first() : null;

                    $collapsed = json_encode([$first ?: 'xlsx']);

                    if ($collapsed !== $schedule->formats) {
                        DB::table('scheduled_reports')
                            ->where('id', $schedule->id)
                            ->update(['formats' => $collapsed]);
                    }
                }
            });
    }
};
