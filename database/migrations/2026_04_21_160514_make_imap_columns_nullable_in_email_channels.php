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
            $table->string('imap_host')->nullable()->change();
            $table->integer('imap_port')->nullable()->change();
            $table->string('imap_encryption')->nullable()->change();
            $table->string('imap_username')->nullable()->change();
            $table->string('imap_password')->nullable()->change();
            $table->string('imap_folder')->nullable()->change();
            $table->string('smtp_host')->nullable()->change();
            $table->integer('smtp_port')->nullable()->change();
            $table->string('smtp_encryption')->nullable()->change();
            $table->string('smtp_username')->nullable()->change();
            $table->string('smtp_password')->nullable()->change();
        });
    }

    public function down(): void
    {
        // intentionally left blank
    }
};
