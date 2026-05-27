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
        Schema::table('email_channels', function (Blueprint $table) {
            $table->string('gmail_client_id')->nullable()->after('imap_password');
            $table->text('gmail_client_secret')->nullable()->after('gmail_client_id');
            $table->text('gmail_refresh_token')->nullable()->after('gmail_client_secret');
            $table->text('gmail_access_token')->nullable()->after('gmail_refresh_token');
            $table->string('gmail_pubsub_topic')->nullable()->after('gmail_access_token');
            $table->unsignedBigInteger('gmail_history_id')->nullable()->after('gmail_pubsub_topic');
            $table->boolean('use_gmail_api')->default(false)->after('gmail_history_id');
        });
    }

    public function down(): void
    {
        Schema::table('email_channels', function (Blueprint $table) {
            $table->dropColumn([
                'gmail_client_id', 'gmail_client_secret', 'gmail_refresh_token',
                'gmail_access_token', 'gmail_pubsub_topic', 'gmail_history_id', 'use_gmail_api',
            ]);
        });
    }
};
