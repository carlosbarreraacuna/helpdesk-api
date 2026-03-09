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
        Schema::create('widget_chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('ticket_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('assigned_agent_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('status', 30)->default('pending'); // pending, active, closed, transferred
            $table->jsonb('search_context')->nullable(); // {query, suggested_articles, opened_article, timestamp}
            $table->timestamp('started_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('assigned_agent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('widget_chat_sessions');
    }
};
