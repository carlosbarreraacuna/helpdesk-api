<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailChannel;
use App\Services\EmailChannelService;
use App\Services\GmailPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GmailWebhookController extends Controller
{
    /**
     * Receives Google Cloud Pub/Sub push notifications.
     * Google POSTs here within ~2-5 seconds of a new Gmail message.
     */
    public function push(Request $request): \Illuminate\Http\JsonResponse
    {
        // Pub/Sub envelope: { "message": { "data": "<base64>", "messageId": "...", "publishTime": "..." } }
        $envelope = $request->json()->all();

        if (empty($envelope['message']['data'])) {
            return response()->json(['status' => 'ok']);
        }

        $data = json_decode(base64_decode($envelope['message']['data']), true);

        if (empty($data['emailAddress']) || empty($data['historyId'])) {
            return response()->json(['status' => 'ok']);
        }

        $emailAddress = $data['emailAddress'];
        $historyId    = (string) $data['historyId'];

        Log::info('Gmail push received', ['email' => $emailAddress, 'historyId' => $historyId]);

        $channel = EmailChannel::where('use_gmail_api', true)
            ->where(function ($q) use ($emailAddress) {
                $q->where('email', $emailAddress)
                  ->orWhere('imap_username', $emailAddress);
            })
            ->where('is_active', true)
            ->first();

        if (!$channel) {
            Log::warning('Gmail push: no active channel found', ['email' => $emailAddress]);
            // Return 200 so Google doesn't retry
            return response()->json(['status' => 'ok']);
        }

        try {
            $gmailService   = new GmailPushService($channel);
            $newMessages    = $gmailService->fetchNewMessages($historyId);
            $emailService   = new EmailChannelService();
            $processed      = 0;

            foreach ($newMessages as $msg) {
                // Simulate the IMAP fetch response format expected by processRawMessage
                // The raw message is already the RFC 2822 content (not wrapped in IMAP literal)
                $result = $emailService->processRawEmailDirectly($msg['raw'], $msg['id'], $channel);
                if ($result) $processed++;
            }

            Log::info('Gmail push processed', ['processed' => $processed, 'total' => count($newMessages)]);
        } catch (\Throwable $e) {
            Log::error('Gmail push processing failed', ['error' => $e->getMessage()]);
            // Still return 200 to avoid Pub/Sub retry storm
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * OAuth2 authorization redirect — sends admin to Google consent screen.
     */
    public function oauthRedirect(Request $request, string $channelId): \Illuminate\Http\RedirectResponse
    {
        $channel     = EmailChannel::findOrFail($channelId);
        $redirectUri = route('gmail.oauth.callback');
        $url         = GmailPushService::getAuthUrl($channel, $redirectUri, $channelId);

        return redirect($url);
    }

    /**
     * OAuth2 callback — exchanges code for tokens, then starts watch().
     */
    public function oauthCallback(Request $request): \Illuminate\Http\JsonResponse
    {
        $code      = $request->query('code');
        $channelId = $request->query('state');

        if (!$code || !$channelId) {
            return response()->json(['error' => 'Missing code or session'], 400);
        }

        $channel     = EmailChannel::findOrFail($channelId);
        $redirectUri = route('gmail.oauth.callback');

        try {
            GmailPushService::exchangeCode($channel, $code, $redirectUri);

            // Start watch immediately after authorization
            $service = new GmailPushService($channel);
            $service->startWatch();

            $channel->update(['use_gmail_api' => true]);

            return response()->json(['success' => true, 'message' => 'Gmail API activado correctamente.']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
