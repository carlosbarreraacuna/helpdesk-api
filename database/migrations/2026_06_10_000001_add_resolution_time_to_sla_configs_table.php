<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sla_configs', function (Blueprint $table) {
            $table->integer('resolution_time_hours')->after('response_time_hours')->default(8);
            $table->integer('work_start_hour')->after('alert_threshold')->default(8);
            $table->integer('work_end_hour')->after('work_start_hour')->default(18);
        });
    }

    public function down(): void
    {
        Schema::table('sla_configs', function (Blueprint $table) {
            $table->dropColumn(['resolution_time_hours', 'work_start_hour', 'work_end_hour']);
        });
    }
};
