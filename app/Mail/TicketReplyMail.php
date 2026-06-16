<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public Ticket $ticket;
    public string $replyBody;
    public string $agentName;
    public string $channel;
    public Collection $history;

    public function __construct(Ticket $ticket, string $replyBody, string $agentName, string $channel = 'email')
    {
        $this->ticket    = $ticket;
        $this->replyBody = $replyBody;
        $this->agentName = $agentName;
        $this->channel   = $channel;

        // Load all public comments except the current reply (last one), ordered chronologically
        $this->history = TicketComment::with('user')
            ->where('ticket_id', $ticket->id)
            ->where('is_internal', false)
            ->orderBy('created_at')
            ->get()
            ->slice(0, -1) // exclude the reply just added
            ->values();
    }

    public function build(): static
    {
        $subject = 'Re: [' . $this->ticket->ticket_number . '] ' . mb_substr($this->ticket->description, 0, 60);

        return $this->subject($subject)
                    ->view('emails.ticket-reply');
    }
}
