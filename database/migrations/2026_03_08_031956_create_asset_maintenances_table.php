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
        Schema::create('asset_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets');
            $table->string('maintenance_type', 20); // preventive|corrective
            $table->string('status', 20)->default('scheduled');
            $table->text('description')->nullable();
            $table->foreignId('service_provider_id')->nullable()->constrained('service_providers')->nullOnDelete();
            $table->date('scheduled_date')->nullable();
            $table->date('executed_date')->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->string('invoice_number', 80)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index('asset_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_maintenances');
    }
};
