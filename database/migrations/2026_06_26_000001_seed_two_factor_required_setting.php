<?php

use App\Models\HelpdeskSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        HelpdeskSetting::firstOrCreate(
            ['key' => 'two_factor_required'],
            ['value' => '0', 'description' => 'Exigir 2FA a administradores y supervisores al iniciar sesión']
        );
    }

    public function down(): void
    {
        HelpdeskSetting::where('key', 'two_factor_required')->delete();
    }
};
