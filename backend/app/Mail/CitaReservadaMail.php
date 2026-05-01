<?php

namespace App\Mail;

use App\Models\Cita;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CitaReservadaMail extends Mailable
{
    use Queueable, SerializesModels, \App\Mail\Concerns\TaggedMail;

    public function __construct(public Cita $cita) {}

    public function build(): self
    {
        return $this
            ->subject('Reserva confirmada — TarotEstrellas')
            ->tagNotif('cita_reservada', $this->cita->cliente_id, $this->cita->id)
            ->view('emails.cita-reservada', ['cita' => $this->cita]);
    }
}
