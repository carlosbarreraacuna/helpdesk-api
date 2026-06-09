<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketValidationResultMail extends Mailable
{
    use Queueable, SerializesModels;

    public Ticket $ticket;
    public string $action;   // 'approved' | 'rejected'
    public ?string $comment;
    public string $requesterName;

    public function __construct(Ticket $ticket, string $action, ?string $comment, string $requesterName)
    {
        $this->ticket        = $ticket;
        $this->action        = $action;
        $this->comment       = $comment;
        $this->requesterName = $requesterName;
    }

    public function build(): static
    {
        $subject = $this->action === 'approved'
            ? "Ticket {$this->ticket->ticket_number} aprobado por el usuario"
            : "Ticket {$this->ticket->ticket_number} rechazado — aún hay problemas";

        return $this->subject($subject)
                    ->view('emails.ticket-validation-result');
    }
}
