<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asset_location_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets');
            $table->foreignId('from_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('changed_by')->constrained('users');
            $table->text('reason')->nullable();
            $table->timestampTz('changed_at')->useCurrent();

            $table->index('asset_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_location_logs');
    }
};
