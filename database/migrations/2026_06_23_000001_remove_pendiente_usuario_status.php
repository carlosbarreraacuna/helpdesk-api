<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $status = DB::table('ticket_statuses')->where('name', 'pendiente_usuario')->first();

        if ($status) {
            $fallback = DB::table('ticket_statuses')->where('name', 'en_progreso')->first();

            if ($fallback) {
                // No tickets should be on this status (never used in code), but
                // reassign defensively in case one was set manually.
                DB::table('tickets')->where('status_id', $status->id)->update(['status_id' => $fallback->id]);
            }

            DB::table('ticket_statuses')->where('id', $status->id)->delete();
            DB::table('ticket_statuses')->where('order', '>', $status->order)->decrement('order');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $exists = DB::table('ticket_statuses')->where('name', 'pendiente_usuario')->exists();

        if (!$exists) {
            DB::table('ticket_statuses')->insert([
                'name'       => 'pendiente_usuario',
                'color'      => '#FB923C',
                'order'      => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
