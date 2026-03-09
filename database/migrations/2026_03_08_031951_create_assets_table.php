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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_type_id')->constrained('asset_types');
            $table->string('name', 150);
            $table->string('internal_code', 80)->nullable()->unique();
            $table->string('serial_number', 100)->nullable()->unique();
            $table->string('inventory_tag', 80)->nullable()->unique();
            $table->string('status', 30)->default('available');
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('current_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('vendor', 150)->nullable();
            $table->date('purchase_date')->nullable();
            $table->date('warranty_end_date')->nullable();
            $table->string('warranty_provider', 150)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_shared')->default(false);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index('asset_type_id');
            $table->index('status');
            $table->index('location_id');
            $table->index('current_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
