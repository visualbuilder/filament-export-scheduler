<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Date range and file format stop being report-level defaults and become
 * properties of a schedule.
 *
 * Both values are pushed down onto every schedule first, so nothing that is
 * currently being delivered changes, and only then are the report columns
 * dropped. Formats collapse to a single entry: the UI now offers one file per
 * schedule, so a row holding both csv and xlsx keeps whichever came first.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('scheduled_reports') || ! Schema::hasTable('custom_reports')) {
            return;
        }

        $hasReportColumns = Schema::hasColumn('custom_reports', 'date_range')
            && Schema::hasColumn('custom_reports', 'formats');

        if ($hasReportColumns) {
            $this->pushDefaultsOntoSchedules();
        }

        $this->collapseScheduleFormats();

        if ($hasReportColumns) {
            Schema::table('custom_reports', function (Blueprint $table) {
                $table->dropColumn(['date_range', 'formats']);
            });
        }
    }

    /**
     * Nothing can be restored here — the values now live on the schedules. Only
     * the columns come back, so a rollback leaves a working schema rather than a
     * broken one.
     */
    public function down(): void
    {
        if (! Schema::hasTable('custom_reports')) {
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
    }

    /**
     * Copy each report's date range and formats onto its schedules, but only
     * where the schedule has not already chosen its own. A schedule with a value
     * was overriding the report before this migration and must keep doing so.
     */
    protected function pushDefaultsOntoSchedules(): void
    {
        DB::table('custom_reports')
            ->select('id', 'date_range', 'formats')
            ->orderBy('id')
            ->chunkById(200, function ($reports) {
                foreach ($reports as $report) {
                    $updates = [];

                    if (filled($report->date_range)) {
                        $updates['date_range'] = $report->date_range;
                    }

                    if (filled($report->formats)) {
                        $updates['formats'] = $report->formats;
                    }

                    foreach ($updates as $column => $value) {
                        DB::table('scheduled_reports')
                            ->where('custom_report_id', $report->id)
                            ->where(fn ($query) => $query
                                ->whereNull($column)
                                ->orWhere($column, '')
                                ->orWhere($column, '[]'))
                            ->update([$column => $value]);
                    }
                }
            });
    }

    /**
     * One file per schedule from here on. Anything empty becomes xlsx, which is
     * the default the form now offers.
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
