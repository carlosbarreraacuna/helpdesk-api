<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;

/**
 * @tags Canales
 */
class GoogleOAuthController extends Controller
{
    public function __construct(private GoogleCalendarService $google) {}

    /** Return the Google OAuth2 consent URL */
    public function redirectUrl()
    {
        return response()->json(['url' => $this->google->getAuthUrl()]);
    }

    /** OAuth2 callback — exchange code, save token, redirect to frontend settings */
    public function callback(Request $request)
    {
        $code  = $request->query('code');
        $error = $request->query('error');

        $frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/');

        if ($error || !$code) {
            return redirect("{$frontendUrl}/settings/integrations?google=error&msg=" . urlencode($error ?? 'No code'));
        }

        try {
            $tokenData = $this->google->exchangeCode($code);

            // Get user email from Google userinfo
            $email = null;
            try {
                $info  = \Illuminate\Support\Facades\Http::withToken($tokenData['access_token'])
                    ->get('https://www.googleapis.com/oauth2/v2/userinfo')->json();
                $email = $info['email'] ?? null;
            } catch (\Throwable) {}

            $this->google->saveToken($tokenData, $email);

            return redirect("{$frontendUrl}/settings/integrations?google=success&email=" . urlencode($email ?? ''));
        } catch (\Throwable $e) {
            return redirect("{$frontendUrl}/settings/integrations?google=error&msg=" . urlencode($e->getMessage()));
        }
    }

    /** Status of the Google connection */
    public function status()
    {
        return response()->json([
            'connected' => $this->google->isConnected(),
            'email'     => $this->google->getConnectedEmail(),
        ]);
    }

    /** Disconnect Google account */
    public function disconnect()
    {
        $this->google->disconnect();
        return response()->json(['message' => 'Desconectado de Google']);
    }
}
