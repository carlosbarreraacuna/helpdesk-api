<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('report_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // 'tickets_by_status'
            $table->string('name'); // 'Tickets por Estado'
            $table->text('description')->nullable();
            $table->enum('type', ['chart', 'table', 'metric', 'export']); // Tipo de reporte
            $table->enum('chart_type', ['bar', 'line', 'pie', 'doughnut', 'area'])->nullable();
            $table->string('icon')->default('BarChart3');
            $table->json('config')->nullable(); // Configuración específica
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('report_templates');
    }
};
