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
        Schema::create('sla_overrides', function (Blueprint $table) {
            $table->id();
            $table->enum('scope', ['agent', 'group']);
            $table->unsignedBigInteger('scope_id');
            $table->string('priority'); // alta, media, baja
            $table->unsignedInteger('response_time_hours');
            $table->unsignedInteger('resolution_time_hours');
            $table->unsignedTinyInteger('alert_threshold')->default(80);
            $table->unsignedTinyInteger('work_start_hour')->default(8);
            $table->unsignedTinyInteger('work_end_hour')->default(18);
            $table->timestamps();

            $table->unique(['scope', 'scope_id', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sla_overrides');
    }
};
