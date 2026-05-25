<?php

namespace App\Mail;

use App\Mail\Concerns\TaggedMail;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SupportTicketUpdatedMail extends Mailable
{
    use Queueable, SerializesModels, TaggedMail;

    public function __construct(
        public SupportTicket $ticket,
        public ?SupportTicketMessage $message = null,
        public ?string $estadoAnterior = null,
    ) {}

    public function build(): self
    {
        return $this
            ->subject("Actualización de soporte {$this->ticket->codigo} — TarotEstrellas")
            ->tagNotif('support_ticket_updated', $this->ticket->user_id, $this->ticket->id)
            ->view('emails.support-ticket-updated', [
                'ticket' => $this->ticket,
                'message' => $this->message,
                'estadoAnterior' => $this->estadoAnterior,
            ]);
    }
}
