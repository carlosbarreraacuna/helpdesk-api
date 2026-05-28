<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedInteger('priority_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // Pivot: agents belonging to a group
        Schema::create('work_group_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_group_id')->constrained('work_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['work_group_id', 'user_id']);
        });

        // Rules: categories that trigger assignment to a group
        Schema::create('work_group_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_group_id')->constrained('work_groups')->cascadeOnDelete();
            $table->foreignId('ticket_category_id')->constrained('ticket_categories')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['work_group_id', 'ticket_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_group_rules');
        Schema::dropIfExists('work_group_user');
        Schema::dropIfExists('work_groups');
    }
};
