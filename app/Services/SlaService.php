<?php

namespace App\Services;

use App\Models\SlaConfig;
use App\Models\Ticket;
use Carbon\Carbon;

class SlaService
{
    private const TIMEZONE = 'America/Bogota';

    /**
     * Calculate SLA due dates for a ticket and persist them.
     */
    public function calculateAndAssign(Ticket $ticket): void
    {
        $config = SlaConfig::forPriority($ticket->priority);
        if (!$config) {
            return;
        }

        $start = Carbon::now(self::TIMEZONE);

        $responseDue   = $this->addBusinessHours($start->copy(), $config->response_time_hours, $config);
        $resolutionDue = $this->addBusinessHours($start->copy(), $config->resolution_time_hours, $config);

        $ticket->update([
            'sla_response_due_at'   => $responseDue->utc(),
            'sla_resolution_due_at' => $resolutionDue->utc(),
            'sla_due_date'          => $resolutionDue->utc(),
        ]);
    }

    /**
     * Add N business hours to a datetime.
     * Business days: Monday–Friday, between work_start_hour and work_end_hour.
     */
    public function addBusinessHours(Carbon $from, int $hours, SlaConfig $config): Carbon
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
        $config = SlaConfig::forPriority($ticket->priority);
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

    private function moveToBusinessHours(Carbon $dt, SlaConfig $config): Carbon
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

    private function nextBusinessDayStart(Carbon $dt, SlaConfig $config): Carbon
    {
        $next = $dt->copy()->addDay()->setTime($config->work_start_hour, 0, 0);
        return $this->moveToBusinessHours($next, $config);
    }
}
