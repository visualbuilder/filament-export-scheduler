<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Splits every export_schedules row into a custom_reports row (the definition) and a
 * scheduled_reports row (the delivery instruction) pointing at it.
 *
 * Note on transactions: DDL is deliberately kept outside DB::transaction(). MySQL
 * implicitly commits on CREATE/DROP TABLE, which ends the transaction underneath
 * Laravel and makes the closing commit fail with "There is no active transaction".
 * Only the data copy is wrapped.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('export_schedules')) {
            return;
        }

        DB::transaction(function () {
            DB::table('export_schedules')->orderBy('id')->each(function ($row) {
                // Read as an array so an install predating any of the later
                // export_schedules migrations does not blow up on a missing column.
                $old = (array) $row;

                $customReportId = DB::table('custom_reports')->insertGetId([
                    'name' => $old['name'] ?? 'Untitled report',
                    'report_type' => $old['report_type'] ?? 'exporter',
                    'sql_query' => $old['sql_query'] ?? null,
                    'exporter' => $old['exporter'] ?? null,
                    'columns' => $old['columns'] ?? null,
                    'filters' => $old['filters'] ?? null,
                    'date_range' => $old['date_range'] ?? null,
                    'formats' => $old['formats'] ?? null,
                    // The recipient is the only ownership signal the old row carries.
                    'owner_type' => $old['owner_type'] ?? null,
                    'owner_id' => $old['owner_id'] ?? null,
                    'visibility' => 'owner',
                    'visible_to_type' => null,
                    'visible_to_ids' => null,
                    'created_at' => $old['created_at'] ?? now(),
                    'updated_at' => $old['updated_at'] ?? now(),
                ]);

                DB::table('scheduled_reports')->insert([
                    'custom_report_id' => $customReportId,
                    'schedule_frequency' => $old['schedule_frequency'] ?? null,
                    'schedule_time' => $old['schedule_time'] ?? '00:00:00',
                    'cron' => $old['cron'] ?? null,
                    'schedule_day_of_week' => $old['schedule_day_of_week'] ?? null,
                    'schedule_day_of_month' => $old['schedule_day_of_month'] ?? null,
                    'schedule_month' => $old['schedule_month'] ?? null,
                    'schedule_start_month' => $old['schedule_start_month'] ?? null,
                    'schedule_timezone' => $old['schedule_timezone'] ?? null,
                    // Left null so both inherit from the report, guaranteeing the
                    // migrated schedule produces byte-identical output to today.
                    'date_range' => null,
                    'formats' => null,
                    'recipient_type' => $old['owner_type'] ?? null,
                    'recipient_id' => $old['owner_id'] ?? null,
                    'cc' => $old['cc'] ?? null,
                    'dynamic_owner_enabled' => $old['dynamic_owner_enabled'] ?? false,
                    'dynamic_owner_attribute' => $old['dynamic_owner_attribute'] ?? null,
                    'enabled' => $old['enabled'] ?? true,
                    'send_empty_report' => $old['send_empty_report'] ?? true,
                    // Preserved verbatim so nothing fires early, late or twice.
                    'next_run_at' => $old['next_run_at'] ?? null,
                    'last_run_at' => $old['last_run_at'] ?? null,
                    'last_successful_run_at' => $old['last_successful_run_at'] ?? null,
                    'created_at' => $old['created_at'] ?? now(),
                    'updated_at' => $old['updated_at'] ?? now(),
                ]);
            });
        });

        Schema::dropIfExists('export_schedules');
    }

    public function down(): void
    {
        if (Schema::hasTable('export_schedules')) {
            return;
        }

        // Outside the transaction below — see the class docblock.
        $this->createLegacyTable();

        if (! Schema::hasTable('custom_reports') || ! Schema::hasTable('scheduled_reports')) {
            return;
        }

        DB::transaction(function () {
            DB::table('scheduled_reports as s')
                ->join('custom_reports as r', 's.custom_report_id', '=', 'r.id')
                // Explicit, and aliased where both tables share a column name: an
                // unqualified select would let one table's date_range and formats
                // silently shadow the other's.
                ->select([
                    'r.name',
                    'r.report_type',
                    'r.sql_query',
                    'r.exporter',
                    'r.columns',
                    'r.filters',
                    'r.date_range as report_date_range',
                    'r.formats as report_formats',
                    's.date_range as schedule_date_range',
                    's.formats as schedule_formats',
                    's.recipient_type',
                    's.recipient_id',
                    's.schedule_frequency',
                    's.schedule_time',
                    's.cron',
                    's.schedule_day_of_week',
                    's.schedule_day_of_month',
                    's.schedule_month',
                    's.schedule_start_month',
                    's.schedule_timezone',
                    's.cc',
                    's.dynamic_owner_enabled',
                    's.dynamic_owner_attribute',
                    's.enabled',
                    's.send_empty_report',
                    's.next_run_at',
                    's.last_run_at',
                    's.last_successful_run_at',
                    's.created_at',
                    's.updated_at',
                ])
                ->orderBy('s.id')
                ->each(function ($row) {
                    DB::table('export_schedules')->insert([
                        'name' => $row->name,
                        'report_type' => $row->report_type,
                        'sql_query' => $row->sql_query,
                        'exporter' => $row->exporter,
                        'columns' => $row->columns,
                        'filters' => $row->filters,
                        // The schedule's override wins, falling back to the report
                        // default — the inverse of how up() split them.
                        'date_range' => $row->schedule_date_range ?? $row->report_date_range,
                        'formats' => $row->schedule_formats ?? $row->report_formats,
                        'owner_type' => $row->recipient_type,
                        'owner_id' => $row->recipient_id,
                        'schedule_frequency' => $row->schedule_frequency,
                        'schedule_time' => $row->schedule_time,
                        'cron' => $row->cron,
                        'schedule_day_of_week' => $row->schedule_day_of_week,
                        'schedule_day_of_month' => $row->schedule_day_of_month,
                        'schedule_month' => $row->schedule_month,
                        'schedule_start_month' => $row->schedule_start_month,
                        'schedule_timezone' => $row->schedule_timezone,
                        'cc' => $row->cc,
                        'dynamic_owner_enabled' => $row->dynamic_owner_enabled,
                        'dynamic_owner_attribute' => $row->dynamic_owner_attribute,
                        'enabled' => $row->enabled,
                        'send_empty_report' => $row->send_empty_report,
                        'next_run_at' => $row->next_run_at,
                        'last_run_at' => $row->last_run_at,
                        'last_successful_run_at' => $row->last_successful_run_at,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);
                });
        });
    }

    protected function createLegacyTable(): void
    {
        Schema::create('export_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('report_type', 20)->default('exporter');
            $table->text('sql_query')->nullable();
            $table->string('exporter', 191)->nullable();
            $table->json('columns')->nullable();
            $table->json('filters')->nullable();
            $table->string('date_range')->nullable();
            $table->json('formats')->nullable();
            $table->nullableMorphs('owner');
            $table->string('schedule_frequency', 20)->nullable();
            $table->time('schedule_time')->default('00:00:00');
            $table->string('cron')->nullable();
            $table->tinyInteger('schedule_day_of_week')->nullable();
            $table->tinyInteger('schedule_day_of_month')->nullable();
            $table->tinyInteger('schedule_month')->nullable();
            $table->tinyInteger('schedule_start_month')->nullable();
            $table->string('schedule_timezone')->nullable();
            $table->json('cc')->nullable();
            $table->boolean('dynamic_owner_enabled')->default(false);
            $table->string('dynamic_owner_attribute')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('send_empty_report')->default(true);
            $table->dateTime('next_run_at')->nullable();
            $table->dateTime('last_run_at')->nullable();
            $table->dateTime('last_successful_run_at')->nullable();
            $table->timestamps();
        });
    }
};
