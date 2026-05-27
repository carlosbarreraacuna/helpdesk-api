<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('display_name')->nullable();
            // IMAP config (incoming)
            $table->string('imap_host');
            $table->integer('imap_port')->default(993);
            $table->string('imap_encryption')->default('ssl');
            $table->string('imap_username');
            $table->string('imap_password');
            $table->string('imap_folder')->default('INBOX');
            // SMTP config (outgoing) - if null, uses app default
            $table->string('smtp_host')->nullable();
            $table->integer('smtp_port')->nullable();
            $table->string('smtp_encryption')->nullable();
            $table->string('smtp_username')->nullable();
            $table->string('smtp_password')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_polled_at')->nullable();
            $table->string('last_uid')->nullable();
            $table->timestamps();
        });

        Schema::create('email_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->string('message_id')->unique();
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body_html')->nullable();
            $table->text('body_text')->nullable();
            $table->string('direction')->default('inbound');
            $table->string('status')->default('received');
            $table->json('attachments')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_messages');
        Schema::dropIfExists('email_channels');
    }
};
