<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_reports', function (Blueprint $table) {
            if (Schema::hasColumn('scheduled_reports', 'date_range')) {
                $table->dropColumn('date_range');
            }
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_reports', function (Blueprint $table) {
            $table->string('date_range')->nullable();
        });
    }
};
