<?php

namespace App\Services;

use App\Models\GoogleCalendarToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleCalendarService
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private string $calendarId = 'primary';

    public function __construct()
    {
        $this->clientId     = config('services.google.client_id', '');
        $this->clientSecret = config('services.google.client_secret', '');
        $this->redirectUri  = config('services.google.redirect_uri', '');
    }

    // ── OAuth2 ────────────────────────────────────────────────────────────────

    public function getAuthUrl(): string
    {
        $params = http_build_query([
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->redirectUri,
            'response_type' => 'code',
            'scope'         => 'https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/userinfo.email',
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => Str::random(16),
        ]);
        return "https://accounts.google.com/o/oauth2/v2/auth?{$params}";
    }

    public function exchangeCode(string $code): array
    {
        $res = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code'          => $code,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $this->redirectUri,
            'grant_type'    => 'authorization_code',
        ]);

        if ($res->failed()) {
            throw new \Exception('Error al obtener token de Google: ' . $res->body());
        }

        return $res->json();
    }

    public function saveToken(array $tokenData, ?string $email = null): GoogleCalendarToken
    {
        GoogleCalendarToken::truncate();

        return GoogleCalendarToken::create([
            'access_token'  => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? '',
            'token_type'    => $tokenData['token_type'] ?? 'Bearer',
            'expires_at'    => time() + ($tokenData['expires_in'] ?? 3600),
            'email'         => $email,
        ]);
    }

    public function getConnectedEmail(): ?string
    {
        return GoogleCalendarToken::first()?->email;
    }

    public function isConnected(): bool
    {
        return GoogleCalendarToken::exists();
    }

    public function disconnect(): void
    {
        GoogleCalendarToken::truncate();
    }

    // ── Access token (refresh if expired) ────────────────────────────────────

    private function getAccessToken(): string
    {
        $token = GoogleCalendarToken::firstOrFail();

        if (!$token->isExpired()) {
            return $token->access_token;
        }

        // Refresh
        $res = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $token->refresh_token,
            'grant_type'    => 'refresh_token',
        ]);

        if ($res->failed()) {
            throw new \Exception('Error al refrescar token de Google');
        }

        $data = $res->json();
        $token->update([
            'access_token' => $data['access_token'],
            'expires_at'   => time() + ($data['expires_in'] ?? 3600),
        ]);

        return $data['access_token'];
    }

    // ── Calendar Events ───────────────────────────────────────────────────────

    /**
     * Create a Google Calendar event with a Meet conference.
     * Returns ['meet_link' => ..., 'event_id' => ...]
     */
    public function createMeetEvent(array $data): array
    {
        $accessToken = $this->getAccessToken();

        // Filter out blank or invalid emails before sending to Google
        $attendees = collect($data['invitees'] ?? [])
            ->filter(fn($inv) => !empty($inv['email']) && filter_var(trim($inv['email']), FILTER_VALIDATE_EMAIL))
            ->map(fn($inv) => ['email' => strtolower(trim($inv['email']))])
            ->values()
            ->toArray();

        $body = [
            'summary'     => $data['title'],
            'description' => $data['description'] ?? '',
            'start'       => [
                'dateTime' => \Carbon\Carbon::parse($data['start_time'])->toRfc3339String(),
                'timeZone' => config('app.timezone', 'America/Bogota'),
            ],
            'end' => [
                'dateTime' => \Carbon\Carbon::parse($data['end_time'])->toRfc3339String(),
                'timeZone' => config('app.timezone', 'America/Bogota'),
            ],
            'attendees'      => $attendees,
            'conferenceData' => [
                'createRequest' => [
                    'requestId'             => Str::uuid()->toString(),
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ],
            ],
            'reminders' => [
                'useDefault' => false,
                'overrides'  => [
                    ['method' => 'email',  'minutes' => 30],
                    ['method' => 'popup',  'minutes' => 10],
                ],
            ],
        ];

        $res = Http::withToken($accessToken)
            ->post("https://www.googleapis.com/calendar/v3/calendars/{$this->calendarId}/events?conferenceDataVersion=1&sendUpdates=all", $body);

        if ($res->failed()) {
            Log::error('Google Calendar create event failed', ['body' => $res->body()]);
            throw new \Exception('Error al crear evento en Google Calendar: ' . $res->json('error.message', $res->body()));
        }

        $event    = $res->json();
        $meetLink = $event['conferenceData']['entryPoints'][0]['uri']
            ?? $event['hangoutLink']
            ?? null;

        return [
            'meet_link' => $meetLink,
            'event_id'  => $event['id'],
        ];
    }

    public function updateEvent(string $eventId, array $data): void
    {
        $accessToken = $this->getAccessToken();

        $body = [];
        if (isset($data['title']))       $body['summary']     = $data['title'];
        if (isset($data['description'])) $body['description'] = $data['description'];
        if (isset($data['start_time']))  $body['start'] = [
            'dateTime' => \Carbon\Carbon::parse($data['start_time'])->toRfc3339String(),
            'timeZone' => config('app.timezone', 'America/Bogota'),
        ];
        if (isset($data['end_time'])) $body['end'] = [
            'dateTime' => \Carbon\Carbon::parse($data['end_time'])->toRfc3339String(),
            'timeZone' => config('app.timezone', 'America/Bogota'),
        ];

        Http::withToken($accessToken)
            ->patch("https://www.googleapis.com/calendar/v3/calendars/{$this->calendarId}/events/{$eventId}?sendUpdates=all", $body);
    }

    public function deleteEvent(string $eventId): void
    {
        $accessToken = $this->getAccessToken();
        Http::withToken($accessToken)
            ->delete("https://www.googleapis.com/calendar/v3/calendars/{$this->calendarId}/events/{$eventId}?sendUpdates=all");
    }
}
