<?php

use App\Models\WidgetChatSession;
use Illuminate\Support\Facades\Broadcast;

// Canal público para notificaciones de nuevos tickets a admins/agentes
Broadcast::channel('tickets.admin', function ($user) {
    return in_array($user->role?->name, ['admin', 'supervisor', 'agente']);
});

Broadcast::channel('widget.session.{sessionId}', function ($user, $sessionId) {
    try {
        \Log::info('Channel auth check', [
            'user_id'    => $user->id,
            'session_id' => $sessionId,
        ]);

        $session = WidgetChatSession::find($sessionId);

        if (!$session) {
            \Log::warning('Channel auth: session not found', ['session_id' => $sessionId]);
            return false;
        }

        if ((int)$user->id === (int)$session->user_id) {
            \Log::info('Channel auth: owner access granted');
            return true;
        }

        if ($session->assigned_agent_id && (int)$user->id === (int)$session->assigned_agent_id) {
            \Log::info('Channel auth: agent access granted');
            return true;
        }

        $role = \DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->where('users.id', $user->id)
            ->value('roles.name');

        $allowed = in_array($role, ['admin', 'supervisor', 'agente']);
        \Log::info('Channel auth: role check', ['role' => $role, 'allowed' => $allowed]);
        return $allowed;
    } catch (\Throwable $e) {
        \Log::error('Channel auth exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        return false;
    }
});
