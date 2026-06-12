<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailChannel;
use App\Services\EmailChannelService;
use App\Services\GmailPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @tags Canales
 */
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

        // Track when the last push was received so the UI stays accurate
        $channel->update(['last_polled_at' => now()]);

        try {
            $gmailService   = new GmailPushService($channel);
            $newMessages    = $gmailService->fetchNewMessages($historyId);
            $emailService   = new EmailChannelService();
            $processed      = 0;

            foreach ($newMessages as $msg) {
                $result = $emailService->processRawEmailDirectly($msg['raw'], $msg['id'], $channel);
                if ($result) $processed++;
            }

            Log::info('Gmail push processed', ['processed' => $processed, 'total' => count($newMessages)]);
        } catch (\Throwable $e) {
            Log::error('Gmail push processing failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
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
    public function oauthCallback(Request $request): \Illuminate\Http\RedirectResponse
    {
        $code      = $request->query('code');
        $channelId = $request->query('state');

        $frontendUrl  = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
        $redirectBase = $frontendUrl . '/admin/email-channels';

        if (!$code || !$channelId) {
            return redirect($redirectBase . '?gmail=error&msg=' . urlencode('Parámetros incompletos en el callback OAuth.'));
        }

        $channel = EmailChannel::find($channelId);
        if (!$channel) {
            return redirect($redirectBase . '?gmail=error&msg=' . urlencode('Canal no encontrado.'));
        }

        $redirectUri = route('gmail.oauth.callback');

        try {
            GmailPushService::exchangeCode($channel, $code, $redirectUri);
            $channel->update(['use_gmail_api' => true]);

            if ($channel->gmail_pubsub_topic) {
                (new GmailPushService($channel))->startWatch();
            }

            return redirect($redirectBase . '?gmail=connected&channel=' . $channelId);
        } catch (\Throwable $e) {
            Log::error('Gmail OAuth callback failed', ['error' => $e->getMessage()]);
            return redirect($redirectBase . '?gmail=error&msg=' . urlencode($e->getMessage()));
        }
    }
}
