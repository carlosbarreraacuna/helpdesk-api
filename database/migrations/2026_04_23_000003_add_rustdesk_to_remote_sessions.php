<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE ticket_remote_sessions DROP CONSTRAINT IF EXISTS ticket_remote_sessions_tool_check");
        DB::statement("ALTER TABLE ticket_remote_sessions ADD CONSTRAINT ticket_remote_sessions_tool_check CHECK (tool IN ('anydesk', 'teamviewer', 'chrome_remote_desktop', 'rustdesk', 'other'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE ticket_remote_sessions DROP CONSTRAINT IF EXISTS ticket_remote_sessions_tool_check");
        DB::statement("ALTER TABLE ticket_remote_sessions ADD CONSTRAINT ticket_remote_sessions_tool_check CHECK (tool IN ('anydesk', 'teamviewer', 'chrome_remote_desktop', 'other'))");
    }
};
