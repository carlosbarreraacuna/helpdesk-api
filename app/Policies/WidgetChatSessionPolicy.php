<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WidgetChatSession;

class WidgetChatSessionPolicy
{
    public function participate(User $user, WidgetChatSession $session): bool
    {
        // El dueño de la sesión o el agente asignado pueden participar
        if ($user->id === $session->user_id) return true;
        if ($user->id === $session->assigned_agent_id) return true;

        // Agentes y admins pueden participar en cualquier sesión
        return in_array($user->role?->name, ['admin', 'supervisor', 'agente']);
    }
}
