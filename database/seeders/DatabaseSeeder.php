<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AreaSeeder::class,
            RoleSeeder::class,
            TicketStatusSeeder::class,
            SlaConfigSeeder::class,
            ModuleSeeder::class,
            PermissionSeeder::class,
            DefaultRolePermissionSeeder::class,
            UserRoleSeeder::class,
            MenuItemSeeder::class,
            MenuItemRoleSeeder::class,
            ReportTemplateSeeder::class,
            ReportTemplateRoleSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
