<?php

namespace App\Services;

use App\Models\SlaConfig;
use App\Models\SlaOverride;
use App\Models\Ticket;
use App\Models\TicketHistory;
use Carbon\Carbon;

class SlaService
{
    private const TIMEZONE = 'America/Bogota';

    /**
     * Calculate SLA due dates for a ticket and persist them.
     *
     * @param Carbon|null $from Start point for the SLA clock. Defaults to now
     *                          (new ticket). Pass the ticket's creation date
     *                          when recalculating an existing ticket so the
     *                          due dates stay anchored to when it was opened.
     */
    public function calculateAndAssign(Ticket $ticket, ?Carbon $from = null): void
    {
        $config = $this->configFor($ticket);
        if (!$config) {
            return;
        }

        $start = ($from ?? Carbon::now())->copy()->timezone(self::TIMEZONE);

        $responseDue   = $this->addBusinessHours($start->copy(), $config->response_time_hours, $config);
        $resolutionDue = $this->addBusinessHours($start->copy(), $config->resolution_time_hours, $config);

        $ticket->update([
            'sla_response_due_at'   => $responseDue->utc(),
            'sla_resolution_due_at' => $resolutionDue->utc(),
            'sla_due_date'          => $resolutionDue->utc(),
        ]);
    }

    /**
     * Resolves which SLA config applies to a ticket, following the
     * precedence: SLA propio del agente asignado > SLA propio del grupo de
     * trabajo del ticket > configuración global por prioridad.
     */
    private function configFor(Ticket $ticket): SlaConfig|SlaOverride|null
    {
        if ($ticket->assigned_to) {
            $agentOverride = SlaOverride::forScope('agent', $ticket->assigned_to, $ticket->priority);
            if ($agentOverride) {
                return $agentOverride;
            }
        }

        if ($ticket->work_group_id) {
            $groupOverride = SlaOverride::forScope('group', $ticket->work_group_id, $ticket->priority);
            if ($groupOverride) {
                return $groupOverride;
            }
        }

        return SlaConfig::forPriority($ticket->priority);
    }

    /**
     * Hypothetical resolution due date for a ticket if it were governed by
     * the SLA matrix of the given scope ('agent' or 'group'), regardless of
     * which source actually wins for the ticket's official SLA. Returns null
     * if that scope has no own SLA matrix configured for this ticket.
     */
    public function hypotheticalDueAt(Ticket $ticket, string $scope): ?Carbon
    {
        $scopeId = $scope === 'agent' ? $ticket->assigned_to : $ticket->work_group_id;
        if (!$scopeId) {
            return null;
        }

        $override = SlaOverride::forScope($scope, $scopeId, $ticket->priority);
        if (!$override) {
            return null;
        }

        $start = Carbon::parse($ticket->created_at);

        return $this->addBusinessHours($start->copy(), $override->resolution_time_hours, $override)->utc();
    }

    /**
     * Recalculate SLA due dates for all currently open tickets using the
     * latest config (global, group or agent matrix), anchored to each
     * ticket's original creation date.
     *
     * @return array{count: int, changed: int}
     */
    public function recalculateOpenTickets(?int $userId = null): array
    {
        $tickets = Ticket::whereNull('resolved_at')
            ->whereNull('closed_at')
            ->get();

        $changed = 0;

        foreach ($tickets as $ticket) {
            if ($this->recalculateTicket($ticket, $userId)) {
                $changed++;
            }
        }

        return ['count' => $tickets->count(), 'changed' => $changed];
    }

    /**
     * Recalculates a single open ticket's SLA due dates, anchored to its
     * creation date, and — if they actually changed — logs a `ticket_history`
     * entry (action `sla_recalculado`) with before/after values for audit.
     *
     * Returns true if the SLA changed, false otherwise (including when the
     * ticket is already resolved/closed, in which case nothing is touched).
     */
    public function recalculateTicket(Ticket $ticket, ?int $userId = null): bool
    {
        if ($ticket->resolved_at || $ticket->closed_at) {
            return false;
        }

        $previousResponse   = $ticket->sla_response_due_at?->copy();
        $previousResolution = $ticket->sla_resolution_due_at?->copy();

        $this->calculateAndAssign($ticket, Carbon::parse($ticket->created_at));
        $ticket->refresh();

        $responseChanged   = $previousResponse?->ne($ticket->sla_response_due_at) ?? $ticket->sla_response_due_at !== null;
        $resolutionChanged = $previousResolution?->ne($ticket->sla_resolution_due_at) ?? $ticket->sla_resolution_due_at !== null;

        if (!$responseChanged && !$resolutionChanged) {
            return false;
        }

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $userId,
            'action'    => 'sla_recalculado',
            'old_value' => sprintf(
                'Respuesta: %s | Resolución: %s',
                $previousResponse?->format('Y-m-d H:i') ?? 'N/A',
                $previousResolution?->format('Y-m-d H:i') ?? 'N/A'
            ),
            'new_value' => sprintf(
                'Respuesta: %s | Resolución: %s',
                $ticket->sla_response_due_at?->format('Y-m-d H:i') ?? 'N/A',
                $ticket->sla_resolution_due_at?->format('Y-m-d H:i') ?? 'N/A'
            ),
        ]);

        return true;
    }

    /**
     * Add N business hours to a datetime.
     * Business days: Monday–Friday, between work_start_hour and work_end_hour.
     */
    public function addBusinessHours(Carbon $from, int $hours, SlaConfig|SlaOverride $config): Carbon
    {
        $current = $from->copy()->timezone(self::TIMEZONE);
        $remaining = $hours;

        // If outside business hours, advance to next business day start
        $current = $this->moveToBusinessHours($current, $config);

        while ($remaining > 0) {
            $endOfDay = $current->copy()->setTime($config->work_end_hour, 0, 0);
            $hoursUntilEnd = $current->diffInMinutes($endOfDay) / 60;

            if ($remaining < $hoursUntilEnd) {
                $current->addMinutes((int) round($remaining * 60));
                $remaining = 0;
            } else {
                $remaining -= $hoursUntilEnd;
                // Move to next business day start
                $current = $this->nextBusinessDayStart($current, $config);
            }
        }

        return $current;
    }

    /**
     * Determine the SLA status of a ticket.
     * Returns: 'met' | 'on_track' | 'at_risk' | 'breached'
     */
    public function getStatus(Ticket $ticket): string
    {
        if (!$ticket->sla_resolution_due_at) {
            return 'on_track';
        }

        // If ticket is closed/resolved, check if it was within SLA
        if ($ticket->closed_at || $ticket->resolved_at) {
            $resolvedAt = $ticket->closed_at ?? $ticket->resolved_at;
            return $resolvedAt->lte($ticket->sla_resolution_due_at) ? 'met' : 'breached';
        }

        $now = Carbon::now();
        $due = $ticket->sla_resolution_due_at;

        if ($now->gt($due)) {
            return 'breached';
        }

        // Calculate usage percentage to detect at_risk
        $config = $this->configFor($ticket);
        if ($config) {
            $totalMinutes   = $config->resolution_time_hours * 60;
            $usedMinutes    = Carbon::parse($ticket->created_at)->diffInMinutes($now);
            $usagePercent   = $totalMinutes > 0 ? ($usedMinutes / $totalMinutes) * 100 : 0;

            if ($usagePercent >= $config->alert_threshold) {
                return 'at_risk';
            }
        }

        return 'on_track';
    }

    /**
     * Find all tickets currently in breach (not yet notified).
     */
    public function findUnnotifiedBreaches(): \Illuminate\Database\Eloquent\Collection
    {
        return Ticket::whereNotNull('sla_resolution_due_at')
            ->where('sla_resolution_due_at', '<=', now())
            ->whereNull('sla_breach_notified_at')
            ->whereNull('closed_at')
            ->whereNull('resolved_at')
            ->with(['assignedAgent.role', 'assignedAgent.area', 'status', 'workGroup'])
            ->get();
    }

    private function moveToBusinessHours(Carbon $dt, SlaConfig|SlaOverride $config): Carbon
    {
        // Skip weekends
        while ($dt->isWeekend()) {
            $dt->addDay()->setTime($config->work_start_hour, 0, 0);
        }

        // Before work hours → move to start of business day
        if ($dt->hour < $config->work_start_hour) {
            $dt->setTime($config->work_start_hour, 0, 0);
        }

        // After work hours → move to next business day
        if ($dt->hour >= $config->work_end_hour) {
            $dt->addDay()->setTime($config->work_start_hour, 0, 0);
            return $this->moveToBusinessHours($dt, $config);
        }

        return $dt;
    }

    private function nextBusinessDayStart(Carbon $dt, SlaConfig|SlaOverride $config): Carbon
    {
        $next = $dt->copy()->addDay()->setTime($config->work_start_hour, 0, 0);
        return $this->moveToBusinessHours($next, $config);
    }
}
