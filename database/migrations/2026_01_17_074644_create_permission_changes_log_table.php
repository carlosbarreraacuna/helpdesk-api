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
        Schema::create('permission_changes_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('changed_by')->constrained('users');
            $table->enum('change_type', ['role_permission', 'user_permission']);
            $table->bigInteger('entity_id');
            $table->foreignId('permission_id')->constrained();
            $table->boolean('old_value')->nullable();
            $table->boolean('new_value')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permission_changes_log');
    }
};
