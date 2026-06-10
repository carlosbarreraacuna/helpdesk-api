<?php

namespace App\Jobs;

use App\Mail\SlaBreachMail;
use App\Models\User;
use App\Services\SlaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class CheckSlaBreaches implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(SlaService $slaService): void
    {
        $breachedTickets = $slaService->findUnnotifiedBreaches();

        foreach ($breachedTickets as $ticket) {
            $recipients = $this->buildRecipients($ticket);

            foreach ($recipients as $email => $name) {
                Mail::to($email)->queue(new SlaBreachMail($ticket, $name));
            }

            $ticket->update(['sla_breach_notified_at' => now()]);
        }
    }

    private function buildRecipients($ticket): array
    {
        $recipients = [];

        // Notify the assigned agent
        if ($ticket->assignedAgent && $ticket->assignedAgent->email) {
            $recipients[$ticket->assignedAgent->email] = $ticket->assignedAgent->name;
        }

        // Notify all active supervisors
        $supervisors = User::whereHas('role', fn($q) => $q->where('name', 'supervisor'))
            ->where('is_active', true)
            ->get();

        foreach ($supervisors as $supervisor) {
            if ($supervisor->email && !isset($recipients[$supervisor->email])) {
                $recipients[$supervisor->email] = $supervisor->name;
            }
        }

        return $recipients;
    }
}
