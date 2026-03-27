<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->timestamps();
        });

        Schema::create('organisations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('primary_contact_id')->nullable()->constrained('contacts')->onDelete('set null');
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('content')->nullable();
            $table->string('owner_type')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->timestamps();
            $table->index(['owner_type', 'owner_id']);
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->timestamp('completed_at')->nullable();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('importer');
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('total_rows');
            $table->unsignedInteger('successful_rows')->default(0);
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });

        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->timestamp('completed_at')->nullable();
            $table->string('file_disk');
            $table->string('file_name')->nullable();
            $table->string('exporter');
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('total_rows');
            $table->unsignedInteger('successful_rows')->default(0);
            $table->unsignedBigInteger('user_id');
            $table->string('user_type')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('failed_import_rows', function (Blueprint $table) {
            $table->id();
            $table->json('data');
            $table->foreignId('import_id')->constrained('imports')->onDelete('cascade');
            $table->text('validation_error')->nullable();
            $table->timestamps();
        });

        Schema::create('export_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('report_type', 20)->default('exporter');
            $table->text('sql_query')->nullable();
            $table->string('exporter')->nullable();
            $table->json('columns')->nullable();
            $table->string('schedule_frequency');
            $table->time('schedule_time')->default('00:00:00');
            $table->string('cron')->nullable();
            $table->unsignedTinyInteger('schedule_day_of_week')->nullable();
            $table->tinyInteger('schedule_day_of_month')->nullable();
            $table->unsignedTinyInteger('schedule_month')->nullable();
            $table->string('schedule_timezone', 50)->default('UTC');
            $table->unsignedTinyInteger('schedule_start_month')->nullable();
            $table->json('filters')->nullable();
            $table->string('formats')->nullable();
            $table->string('date_range')->nullable();
            $table->string('owner_type')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->json('cc')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('dynamic_owner_enabled')->default(false);
            $table->string('dynamic_owner_attribute')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('last_successful_run_at')->nullable();
            $table->timestamps();
            $table->index(['owner_type', 'owner_id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('export_schedules');
        Schema::dropIfExists('failed_import_rows');
        Schema::dropIfExists('exports');
        Schema::dropIfExists('imports');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('organisations');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('users');
    }
};
