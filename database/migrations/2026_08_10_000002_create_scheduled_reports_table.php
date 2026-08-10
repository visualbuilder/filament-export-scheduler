<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_report_id')->constrained('custom_reports')->cascadeOnDelete();
            $table->string('schedule_frequency', 20)->nullable();
            $table->time('schedule_time')->default('00:00:00');
            $table->string('cron')->nullable();
            $table->tinyInteger('schedule_day_of_week')->nullable();
            $table->tinyInteger('schedule_day_of_month')->nullable();
            $table->tinyInteger('schedule_month')->nullable();
            $table->tinyInteger('schedule_start_month')->nullable();
            $table->string('schedule_timezone')->nullable();
            $table->string('date_range')->nullable();
            $table->json('formats')->nullable();
            $table->nullableMorphs('recipient');
            $table->json('cc')->nullable();
            $table->boolean('dynamic_owner_enabled')->default(false);
            $table->string('dynamic_owner_attribute')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('send_empty_report')->default(true);
            $table->dateTime('next_run_at')->nullable();
            $table->dateTime('last_run_at')->nullable();
            $table->dateTime('last_successful_run_at')->nullable();
            $table->timestamps();

            $table->index(['custom_report_id']);
            $table->index(['enabled', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_reports');
    }
};
