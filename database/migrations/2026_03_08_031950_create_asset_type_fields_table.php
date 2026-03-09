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
        Schema::create('asset_type_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_type_id')->constrained('asset_types')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('display_name', 100);
            $table->string('field_type', 30); // text|number|date|select|boolean
            $table->jsonb('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_identifier')->default(false);
            $table->integer('order_index')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_type_fields');
    }
};
