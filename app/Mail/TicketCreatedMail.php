<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Ticket $ticket;
    public ?string $botContext;
    public ?string $botKbArticleTitle;
    public ?bool $botKbHelpful;
    public bool $isAgentNotification;

    public function __construct(
        Ticket $ticket,
        ?string $botContext = null,
        ?string $botKbArticleTitle = null,
        ?bool $botKbHelpful = null,
        bool $isAgentNotification = false
    ) {
        $this->ticket               = $ticket;
        $this->botContext           = $botContext;
        $this->botKbArticleTitle    = $botKbArticleTitle;
        $this->botKbHelpful         = $botKbHelpful;
        $this->isAgentNotification  = $isAgentNotification;
    }

    public function build(): static
    {
        $subject = $this->isAgentNotification
            ? "Nuevo ticket asignado: {$this->ticket->ticket_number}"
            : "Confirmación de ticket: {$this->ticket->ticket_number}";

        return $this->subject($subject)
                    ->view('emails.ticket-created');
    }
}
