<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('validation_requested_at')->nullable()->after('closed_at');
            $table->timestamp('validation_deadline')->nullable()->after('validation_requested_at');
            $table->string('validation_token', 64)->nullable()->unique()->after('validation_deadline');
            $table->timestamp('validation_approved_at')->nullable()->after('validation_token');
            $table->unsignedTinyInteger('validation_rejected_count')->default(0)->after('validation_approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn([
                'validation_requested_at',
                'validation_deadline',
                'validation_token',
                'validation_approved_at',
                'validation_rejected_count',
            ]);
        });
    }
};
