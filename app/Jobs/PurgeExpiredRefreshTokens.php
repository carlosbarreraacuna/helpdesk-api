<?php

namespace App\Jobs;

use App\Models\RefreshToken;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PurgeExpiredRefreshTokens implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        RefreshToken::where('expires_at', '<', now())->delete();
        RefreshToken::whereNotNull('revoked_at')
            ->where('revoked_at', '<', now()->subDays(30))
            ->delete();
    }
}
