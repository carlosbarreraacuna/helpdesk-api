<?php

namespace App\Mail;

use App\Models\User;
use App\Models\WhatsAppConversation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AsesorSolicitadoWhatsApp extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $conversation;

    public function __construct(User $user, WhatsAppConversation $conversation)
    {
        $this->user = $user;
        $this->conversation = $conversation;
    }

    public function build()
    {
        return $this->subject('Usuario solicita asesor vía WhatsApp')
                    ->view('emails.asesor-solicitado-whatsapp');
    }
}
