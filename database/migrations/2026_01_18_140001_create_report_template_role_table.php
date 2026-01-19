<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('report_template_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_template_id')->constrained('report_templates')->onDelete('cascade');
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->boolean('can_view')->default(true);
            $table->boolean('can_export')->default(false);
            $table->timestamps();
            
            $table->unique(['report_template_id', 'role_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('report_template_role');
    }
};
