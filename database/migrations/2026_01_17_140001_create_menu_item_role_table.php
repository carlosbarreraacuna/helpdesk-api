<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('menu_item_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained('menu_items')->onDelete('cascade');
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
            
            $table->unique(['menu_item_id', 'role_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('menu_item_role');
    }
};
