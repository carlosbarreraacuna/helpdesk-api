<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmailChannelSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('email_channels')->insertOrIgnore([
            'name'                => 'helpdesk',
            'email'               => env('GMAIL_CHANNEL_EMAIL'),
            'gmail_client_id'     => env('GMAIL_CLIENT_ID'),
            'gmail_client_secret' => env('GMAIL_CLIENT_SECRET'),
            'is_active'           => true,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
    }
}
