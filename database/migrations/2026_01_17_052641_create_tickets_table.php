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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique(); // TKT-2025-0001
            $table->string('requester_name');
            $table->string('requester_email');
            $table->string('requester_area');
            $table->text('description');
            $table->string('attachment_path')->nullable();
            $table->string('verification_code', 6); // código de 6 dígitos
            $table->enum('priority', ['alta', 'media', 'baja'])->default('media');
            $table->foreignId('status_id')->constrained('ticket_statuses');
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('sla_due_date')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
