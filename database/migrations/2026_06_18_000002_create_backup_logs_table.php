<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['backup', 'restore']);
            $table->enum('status', ['pending', 'running', 'success', 'failed']);
            $table->string('timestamp_key');
            $table->string('db_dump_path')->nullable();
            $table->string('manifest_path')->nullable();
            $table->unsignedBigInteger('db_dump_size_bytes')->nullable();
            $table->unsignedInteger('attachments_object_count')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('source_backup_id')->nullable()->constrained('backup_logs')->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('purged_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_logs');
    }
};
