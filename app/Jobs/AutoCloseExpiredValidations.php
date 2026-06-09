<?php

namespace App\Jobs;

use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\TicketStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AutoCloseExpiredValidations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $pendingStatus = TicketStatus::where('name', 'pendiente_validacion')->first();
        $closedStatus  = TicketStatus::where('name', 'cerrado')->first();

        if (!$pendingStatus || !$closedStatus) return;

        $expired = Ticket::where('status_id', $pendingStatus->id)
            ->whereNotNull('validation_deadline')
            ->where('validation_deadline', '<=', now())
            ->get();

        foreach ($expired as $ticket) {
            $ticket->update([
                'status_id'             => $closedStatus->id,
                'closed_at'             => now(),
                'validation_approved_at'=> now(),
                'validation_token'      => null,
            ]);

            TicketHistory::create([
                'ticket_id' => $ticket->id,
                'user_id'   => null,
                'action'    => 'cerrado',
                'new_value' => 'Cerrado automáticamente — el usuario no respondió a la validación dentro del plazo.',
            ]);
        }
    }
}
