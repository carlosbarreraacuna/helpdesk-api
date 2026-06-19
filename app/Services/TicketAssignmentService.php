<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\WorkGroup;
use App\Models\TicketHistory;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class TicketAssignmentService
{
    public function __construct(private readonly SlaService $slaService) {}

    /**
     * Tries to auto-assign a ticket to the best matching work group and agent.
     * Called right after ticket creation.
     */
    public function assign(Ticket $ticket): bool
    {
        if (!$ticket->category_id) {
            $this->notifyAdminNoGroup($ticket, 'El ticket no tiene categoría asignada.');
            return false;
        }

        // Find the highest-priority active group that covers this category
        $group = WorkGroup::where('is_active', true)
            ->whereHas('rules', fn($q) => $q->where('ticket_category_id', $ticket->category_id))
            ->orderBy('priority_order')
            ->first();

        if (!$group) {
            $this->notifyAdminNoGroup($ticket, 'Ningún grupo de trabajo cubre la categoría del ticket.');
            return false;
        }

        // Pick agent with fewest open tickets inside the group
        $agent = $this->pickAgent($group);

        $this->doAssign($ticket, $group, $agent);
        return true;
    }

    /**
     * Returns a ticket to its group queue (removes agent assignment).
     * Called when an agent rejects/returns a ticket.
     */
    public function returnToGroup(Ticket $ticket, User $agent, string $reason = ''): void
    {
        $ticket->update([
            'assigned_to' => null,
            'status_id'   => TicketStatus::where('name', 'nuevo')->value('id'),
        ]);

        $this->slaService->recalculateTicket($ticket, $agent->id);

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $agent->id,
            'action'    => 'devuelto_al_grupo',
            'new_value' => $reason ?: 'Devuelto a la cola del grupo.',
        ]);
    }

    // ─────────────────────────────────────────────

    private function pickAgent(WorkGroup $group): ?User
    {
        $openStatusIds = TicketStatus::whereIn('name', ['nuevo', 'asignado', 'en_progreso', 'escalado'])
            ->pluck('id');

        return $group->agents()
            ->where('is_active', true)
            ->withCount(['assignedTickets as open_count' => fn($q) => $q->whereIn('status_id', $openStatusIds)])
            ->orderBy('open_count')
            ->first();
    }

    private function doAssign(Ticket $ticket, WorkGroup $group, ?User $agent): void
    {
        $ticket->update([
            'work_group_id' => $group->id,
            'assigned_to'   => $agent?->id,
            'status_id'     => TicketStatus::where('name', $agent ? 'asignado' : 'nuevo')->value('id'),
        ]);

        // Now that the group (and possibly the agent) are known, calculate
        // the SLA using the agent/group matrix if configured, else the global.
        $this->slaService->calculateAndAssign($ticket, $ticket->created_at);

        $groupLabel = "Grupo: {$group->name}";
        $agentLabel = $agent ? " → Agente: {$agent->name}" : ' → Sin agente disponible en el grupo.';

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'action'    => 'asignado_automatico',
            'new_value' => $groupLabel . $agentLabel,
        ]);

        Log::info("Ticket {$ticket->ticket_number} auto-assigned", [
            'group'    => $group->name,
            'agent_id' => $agent?->id,
        ]);
    }

    private function notifyAdminNoGroup(Ticket $ticket, string $reason): void
    {
        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'action'    => 'sin_grupo',
            'new_value' => $reason,
        ]);

        // Broadcast to admin channel so the real-time panel shows the alert
        broadcast(new \App\Events\TicketCreated($ticket));

        Log::warning("Ticket {$ticket->ticket_number} has no matching group: {$reason}");
    }
}
