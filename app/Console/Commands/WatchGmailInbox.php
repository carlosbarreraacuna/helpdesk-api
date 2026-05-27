<?php

namespace App\Console\Commands;

use App\Models\EmailChannel;
use App\Services\GmailPushService;
use Illuminate\Console\Command;

class WatchGmailInbox extends Command
{
    protected $signature   = 'gmail:watch {--channel= : Specific channel ID}';
    protected $description = 'Start or renew Gmail Pub/Sub push watch for all active Gmail API channels';

    public function handle(): int
    {
        $query = EmailChannel::where('use_gmail_api', true)->where('is_active', true);

        if ($channelId = $this->option('channel')) {
            $query->where('id', $channelId);
        }

        $channels = $query->get();

        if ($channels->isEmpty()) {
            $this->warn('No active Gmail API channels found.');
            return 0;
        }

        foreach ($channels as $channel) {
            $this->info("Watching channel [{$channel->id}] {$channel->email}...");
            try {
                $service = new GmailPushService($channel);
                $ok      = $service->startWatch();
                $this->info($ok ? '  ✓ Watch started.' : '  ✗ Watch failed.');
            } catch (\Throwable $e) {
                $this->error("  Error: {$e->getMessage()}");
            }
        }

        return 0;
    }
}
