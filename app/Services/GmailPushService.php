<?php

namespace App\Services;

use App\Models\EmailChannel;
use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Google\Service\Gmail\WatchRequest;
use Illuminate\Support\Facades\Log;

class GmailPushService
{
    private GoogleClient $client;

    public function __construct(private EmailChannel $channel)
    {
        $this->client = new GoogleClient();
        $this->client->setClientId($channel->gmail_client_id);
        $this->client->setClientSecret($channel->gmail_client_secret);
        $this->client->setAccessType('offline');
        $this->client->setScopes([Gmail::GMAIL_READONLY, Gmail::GMAIL_MODIFY]);
        $this->refreshTokenIfNeeded();
    }

    /**
     * Call gmail.watch() to start / renew push notifications.
     * Must be called once and re-called every ~7 days.
     */
    public function startWatch(): bool
    {
        try {
            $gmail     = new Gmail($this->client);
            $watchReq  = new WatchRequest();
            $watchReq->setTopicName($this->channel->gmail_pubsub_topic);
            $watchReq->setLabelIds(['INBOX']);

            $response = $gmail->users->watch('me', $watchReq);

            $this->channel->update([
                'gmail_history_id' => $response->getHistoryId(),
            ]);

            Log::info('Gmail watch started', [
                'channel'    => $this->channel->id,
                'history_id' => $response->getHistoryId(),
                'expiration' => $response->getExpiration(),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Gmail watch failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Called by the Pub/Sub webhook. Fetches new messages since lastHistoryId.
     * Returns array of raw RFC 2822 message strings.
     */
    public function fetchNewMessages(string $newHistoryId): array
    {
        $lastHistoryId = $this->channel->gmail_history_id;

        Log::info('Gmail fetchNewMessages', [
            'channel'       => $this->channel->id,
            'lastHistoryId' => $lastHistoryId,
            'newHistoryId'  => $newHistoryId,
        ]);

        if (!$lastHistoryId) {
            // No baseline stored — can't diff history. Save current ID and skip this batch.
            // Re-run gmail:watch to get a fresh baseline and process future emails.
            Log::warning('Gmail fetchNewMessages: no lastHistoryId stored — skipping batch and saving baseline', [
                'channel'      => $this->channel->id,
                'newHistoryId' => $newHistoryId,
            ]);
            $this->channel->update(['gmail_history_id' => $newHistoryId]);
            return [];
        }

        try {
            $gmail   = new Gmail($this->client);
            $history = $gmail->users_history->listUsersHistory('me', [
                'startHistoryId' => $lastHistoryId,
                'historyTypes'   => ['messageAdded'],
                'labelId'        => 'INBOX',
            ]);

            $messageIds = [];
            foreach ($history->getHistory() ?? [] as $record) {
                foreach ($record->getMessagesAdded() ?? [] as $added) {
                    $messageIds[] = $added->getMessage()->getId();
                }
            }

            // Deduplicate
            $messageIds = array_unique($messageIds);

            Log::info('Gmail history fetched', [
                'channel'    => $this->channel->id,
                'messageIds' => $messageIds,
            ]);

            $rawMessages = [];
            foreach ($messageIds as $id) {
                $raw = $this->fetchRaw($gmail, $id);
                if ($raw) {
                    $rawMessages[] = ['id' => $id, 'raw' => $raw];
                }
            }

            // Advance history cursor
            $this->channel->update(['gmail_history_id' => $newHistoryId]);

            return $rawMessages;
        } catch (\Throwable $e) {
            Log::error('Gmail fetchNewMessages failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Fetch a single message as raw RFC 2822 string.
     */
    private function fetchRaw(Gmail $gmail, string $messageId): ?string
    {
        try {
            $message = $gmail->users_messages->get('me', $messageId, ['format' => 'raw']);
            $raw     = $message->getRaw();
            // Gmail returns base64url-encoded raw message
            return base64_decode(strtr($raw, '-_', '+/'));
        } catch (\Throwable $e) {
            Log::error('Gmail fetchRaw failed', ['id' => $messageId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function refreshTokenIfNeeded(): void
    {
        $tokenJson = $this->channel->gmail_access_token;
        $token     = $tokenJson ? json_decode($tokenJson, true) : [];

        if ($token) {
            $this->client->setAccessToken($token);
        }

        if ($this->client->isAccessTokenExpired()) {
            if (!$this->channel->gmail_refresh_token) {
                throw new \RuntimeException('No Gmail refresh token stored. Re-authorize the channel.');
            }
            $refreshed = $this->client->fetchAccessTokenWithRefreshToken($this->channel->gmail_refresh_token);
            if (isset($refreshed['error'])) {
                throw new \RuntimeException(
                    'Gmail token refresh failed: ' . ($refreshed['error_description'] ?? $refreshed['error'])
                );
            }
            $newToken = $this->client->getAccessToken();
            $this->channel->update(['gmail_access_token' => json_encode($newToken)]);
        }
    }

    /**
     * Exchange an authorization code for tokens (used during OAuth setup).
     */
    public static function exchangeCode(EmailChannel $channel, string $code, string $redirectUri): array
    {
        $client = new GoogleClient();
        $client->setClientId($channel->gmail_client_id);
        $client->setClientSecret($channel->gmail_client_secret);
        $client->setRedirectUri($redirectUri);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new \RuntimeException('OAuth error: ' . $token['error_description']);
        }

        $channel->update([
            'gmail_access_token'  => json_encode($token),
            'gmail_refresh_token' => $token['refresh_token'] ?? $channel->gmail_refresh_token,
        ]);

        return $token;
    }

    /**
     * Build the Google OAuth2 authorization URL.
     */
    public static function getAuthUrl(EmailChannel $channel, string $redirectUri, string $state = ''): string
    {
        $client = new GoogleClient();
        $client->setClientId($channel->gmail_client_id);
        $client->setClientSecret($channel->gmail_client_secret);
        $client->setRedirectUri($redirectUri);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setScopes([Gmail::GMAIL_READONLY, Gmail::GMAIL_MODIFY]);
        if ($state) {
            $client->setState($state);
        }

        return $client->createAuthUrl();
    }
}
