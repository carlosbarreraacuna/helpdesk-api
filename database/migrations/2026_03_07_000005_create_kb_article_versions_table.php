<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_article_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('kb_articles')->onDelete('cascade');
            $table->integer('version_number');
            $table->string('title', 255);
            $table->longText('content');
            $table->string('change_summary', 500)->nullable();
            $table->foreignId('editor_id')->constrained('users');
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->unique(['article_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_article_versions');
    }
};
