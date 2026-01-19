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
        Schema::table('roles', function (Blueprint $table) {
            if (!Schema::hasColumn('roles', 'display_name')) {
                $table->string('display_name')->nullable()->after('name');
            }
        });
        
        // Actualizar los roles existentes con display_name basado en name
        if (!Schema::hasColumn('roles', 'display_name')) {
            \DB::table('roles')->update([
                'display_name' => \DB::raw("UPPER(SUBSTRING(name FROM 1 FOR 1)) || SUBSTRING(name FROM 2)")
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (Schema::hasColumn('roles', 'display_name')) {
                $table->dropColumn('display_name');
            }
        });
    }
};
