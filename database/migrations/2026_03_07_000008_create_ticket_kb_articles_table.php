<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_kb_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->onDelete('cascade');
            $table->foreignId('article_id')->constrained('kb_articles')->onDelete('cascade');
            $table->foreignId('linked_by')->constrained('users');
            $table->timestamp('linked_at')->useCurrent();

            $table->unique(['ticket_id', 'article_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_kb_articles');
    }
};
