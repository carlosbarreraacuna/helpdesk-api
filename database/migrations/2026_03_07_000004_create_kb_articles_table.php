<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('slug', 280)->unique();
            $table->foreignId('category_id')->constrained('kb_categories');
            $table->foreignId('subcategory_id')->nullable()->constrained('kb_subcategories')->nullOnDelete();
            $table->foreignId('author_id')->constrained('users');
            $table->string('status', 20)->default('draft');
            $table->integer('current_version')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->integer('views_count')->default(0);
            $table->integer('useful_count')->default(0);
            $table->integer('not_useful_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_articles');
    }
};
