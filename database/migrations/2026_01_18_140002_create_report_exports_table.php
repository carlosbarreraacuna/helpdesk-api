<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('report_template_id')->constrained('report_templates');
            $table->string('file_path');
            $table->string('file_format'); // pdf, excel, csv
            $table->json('filters')->nullable();
            $table->timestamp('exported_at');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('report_exports');
    }
};
