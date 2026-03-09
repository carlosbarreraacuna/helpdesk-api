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
        Schema::create('asset_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('assigned_by')->constrained('users');
            $table->timestampTz('assigned_at')->useCurrent();
            $table->timestampTz('returned_at')->nullable();
            $table->text('reason')->nullable();
            $table->text('return_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('asset_id');
            $table->index('user_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_assignments');
    }
};
