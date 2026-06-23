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
        $pendiente = DB::table('ticket_statuses')->where('name', 'pendiente_validacion')->first();
        $resuelto  = DB::table('ticket_statuses')->where('name', 'resuelto')->first();

        if ($pendiente && $resuelto) {
            DB::table('ticket_statuses')->where('id', $pendiente->id)->update(['order' => $resuelto->order]);
            DB::table('ticket_statuses')->where('id', $resuelto->id)->update(['order' => $pendiente->order]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $pendiente = DB::table('ticket_statuses')->where('name', 'pendiente_validacion')->first();
        $resuelto  = DB::table('ticket_statuses')->where('name', 'resuelto')->first();

        if ($pendiente && $resuelto) {
            DB::table('ticket_statuses')->where('id', $pendiente->id)->update(['order' => $resuelto->order]);
            DB::table('ticket_statuses')->where('id', $resuelto->id)->update(['order' => $pendiente->order]);
        }
    }
};
