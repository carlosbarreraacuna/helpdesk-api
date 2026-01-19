<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // 'dashboard', 'tickets', etc
            $table->string('label'); // 'Dashboard', 'Tickets'
            $table->string('icon')->nullable(); // 'home', 'ticket'
            $table->string('route'); // '/dashboard', '/tickets'
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->onDelete('cascade');
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false); // No se puede eliminar
            $table->json('metadata')->nullable(); // badge, color, etc
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('menu_items');
    }
};
