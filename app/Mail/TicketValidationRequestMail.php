<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketValidationRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public Ticket $ticket;
    public string $validationUrl;

    public function __construct(Ticket $ticket, string $validationUrl)
    {
        $this->ticket        = $ticket;
        $this->validationUrl = $validationUrl;
    }

    public function build(): static
    {
        return $this->subject("Valida la resolución de tu ticket {$this->ticket->ticket_number}")
                    ->view('emails.ticket-validation-request');
    }
}
