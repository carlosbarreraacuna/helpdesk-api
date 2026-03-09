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
        Schema::create('asset_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets');
            $table->string('event_type', 40);
            $table->text('description');
            $table->foreignId('performed_by')->constrained('users');
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('occurred_at')->useCurrent();

            $table->index('asset_id');
            $table->index('event_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_events');
    }
};
