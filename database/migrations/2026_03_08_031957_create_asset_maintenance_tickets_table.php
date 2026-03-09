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
        Schema::create('asset_maintenance_tickets', function (Blueprint $table) {
            $table->foreignId('maintenance_id')->constrained('asset_maintenances')->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->primary(['maintenance_id', 'ticket_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_maintenance_tickets');
    }
};
