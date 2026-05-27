<?php

namespace App\Console\Commands;

use App\Models\EmailChannel;
use App\Services\EmailChannelService;
use Illuminate\Console\Command;

class PollEmailInbox extends Command
{
    protected $signature = 'email:poll
                            {--channel= : ID of a specific channel to poll}
                            {--since= : Only process emails since this date (Y-m-d). Default: today}
                            {--limit=50 : Max emails to process per channel}';

    protected $description = 'Poll active email channels and convert new emails to tickets';

    public function handle(EmailChannelService $service): int
    {
        $query = EmailChannel::where('is_active', true);

        if ($channelId = $this->option('channel')) {
            $query->where('id', $channelId);
        }

        $since = $this->option('since')
            ? new \DateTime($this->option('since'))
            : new \DateTime('today');

        $limit = (int) $this->option('limit');

        // Skip channels that use Gmail API push — they are handled by the webhook
        $channels = $query->where('use_gmail_api', false)->get();

        if ($channels->isEmpty()) {
            $this->info('No IMAP channels to poll (all channels use Gmail API or none are active).');
            return self::SUCCESS;
        }

        foreach ($channels as $channel) {
            $this->info("Polling channel: {$channel->name} <{$channel->email}> (since: {$since->format('Y-m-d')}, limit: {$limit})");
            try {
                $count = $service->pollChannel($channel, $since, $limit);
                $this->info("  → {$count} new email(s) processed.");
            } catch (\Throwable $e) {
                $this->error("  → Error: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
