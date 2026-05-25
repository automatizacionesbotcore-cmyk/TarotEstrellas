<?php

namespace App\Mail;

use App\Mail\Concerns\TaggedMail;
use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SupportTicketCreatedMail extends Mailable
{
    use Queueable, SerializesModels, TaggedMail;

    public function __construct(public SupportTicket $ticket) {}

    public function build(): self
    {
        return $this
            ->subject("Solicitud de soporte {$this->ticket->codigo} recibida — TarotEstrellas")
            ->tagNotif('support_ticket_created', $this->ticket->user_id, $this->ticket->id)
            ->view('emails.support-ticket-created', ['ticket' => $this->ticket]);
    }
}
