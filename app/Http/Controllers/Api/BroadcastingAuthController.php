<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\WidgetChatSession;
use Illuminate\Support\Facades\DB;

class BroadcastingAuthController extends Controller
{
    public function __invoke(Request $request)
    {
        $user     = $request->user('sanctum');
        $socketId = $request->input('socket_id');
        $channel  = $request->input('channel_name');

        \Log::info('BroadcastAuth attempt', ['user_id' => $user?->id, 'channel' => $channel]);

        if (!$user || !$socketId || !$channel) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $authorized = $this->checkAuthorization($user, $channel);

        \Log::info('BroadcastAuth result', ['authorized' => $authorized, 'channel' => $channel, 'user_id' => $user->id]);

        if (!$authorized) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $secret    = config('reverb.apps.apps.0.secret');
        $appKey    = config('reverb.apps.apps.0.key');
        $signature = hash_hmac('sha256', "{$socketId}:{$channel}", $secret);

        return response()->json(['auth' => "{$appKey}:{$signature}"]);
    }

    private function checkAuthorization($user, string $channel): bool
    {
        if (str_starts_with($channel, 'private-widget.session.')) {
            $sessionId = (int) str_replace('private-widget.session.', '', $channel);
            $session   = WidgetChatSession::find($sessionId);

            if (!$session) return false;

            if ((int)$user->id === (int)$session->user_id) return true;
            if ($session->assigned_agent_id && (int)$user->id === (int)$session->assigned_agent_id) return true;

            $roleName = DB::table('roles')->where('id', $user->role_id)->value('name');
            return in_array($roleName, ['admin', 'supervisor', 'agente']);
        }

        if (str_starts_with($channel, 'private-tickets.') || str_starts_with($channel, 'presence-tickets.')) {
            $roleName = DB::table('roles')->where('id', $user->role_id)->value('name');
            return in_array($roleName, ['admin', 'supervisor', 'agente']);
        }

        return false;
    }
}
