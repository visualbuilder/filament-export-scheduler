<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_reports', function (Blueprint $table) {
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
            $table->string('visibility', 20)->default('owner');
            $table->string('visible_to_type', 191)->nullable();
            $table->json('visible_to_ids')->nullable();
            $table->timestamps();

            $table->index(['visibility', 'owner_type', 'owner_id']);
            $table->index(['visible_to_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_reports');
    }
};
