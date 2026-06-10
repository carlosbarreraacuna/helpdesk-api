<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('sla_response_due_at')->nullable()->after('sla_due_date');
            $table->timestamp('sla_resolution_due_at')->nullable()->after('sla_response_due_at');
            $table->timestamp('sla_response_met_at')->nullable()->after('sla_resolution_due_at');
            $table->timestamp('sla_breach_notified_at')->nullable()->after('sla_response_met_at');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn([
                'sla_response_due_at',
                'sla_resolution_due_at',
                'sla_response_met_at',
                'sla_breach_notified_at',
            ]);
        });
    }
};
