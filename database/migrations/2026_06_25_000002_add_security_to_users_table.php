<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 2FA
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->boolean('two_factor_enabled')->default(false)->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_enabled');

            // Password policy
            $table->timestamp('password_changed_at')->nullable()->after('two_factor_confirmed_at');
            $table->timestamp('password_expires_at')->nullable()->after('password_changed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret', 'two_factor_enabled', 'two_factor_confirmed_at',
                'password_changed_at', 'password_expires_at',
            ]);
        });
    }
};
