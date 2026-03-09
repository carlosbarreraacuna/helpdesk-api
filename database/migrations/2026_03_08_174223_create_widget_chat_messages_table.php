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
        Schema::create('widget_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('widget_chat_sessions')->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->string('sender_type', 20)->default('user'); // user, agent, system
            $table->text('body');
            $table->string('attachment_path', 500)->nullable();
            $table->string('attachment_name', 255)->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('widget_chat_messages');
    }
};
