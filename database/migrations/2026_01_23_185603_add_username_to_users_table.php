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
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->after('name');
        });
        
        // Agregar valores por defecto para usuarios existentes
        \DB::statement('UPDATE users SET username = CONCAT(LOWER(SUBSTRING(name, 1, 1)), REPLACE(LOWER(SUBSTRING(name FROM POSITION(\' \' IN name) + 1)), \' \', \'\')) WHERE username IS NULL');
        
        // Luego hacer el campo único y no nulo
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }
};
