<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_location_logs', function (Blueprint $table) {
            $table->dropForeign(['from_location_id']);
            $table->dropForeign(['to_location_id']);
            $table->dropColumn(['from_location_id', 'to_location_id']);
            $table->foreignId('from_area_id')->nullable()->constrained('areas')->nullOnDelete()->after('asset_id');
            $table->foreignId('to_area_id')->nullable()->constrained('areas')->nullOnDelete()->after('from_area_id');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
            $table->dropColumn('area_id');
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete()->after('status');
        });

        Schema::table('asset_location_logs', function (Blueprint $table) {
            $table->dropForeign(['from_area_id']);
            $table->dropForeign(['to_area_id']);
            $table->dropColumn(['from_area_id', 'to_area_id']);
            $table->foreignId('from_location_id')->nullable()->constrained('locations')->nullOnDelete()->after('asset_id');
            $table->foreignId('to_location_id')->nullable()->constrained('locations')->nullOnDelete()->after('from_location_id');
        });
    }
};
