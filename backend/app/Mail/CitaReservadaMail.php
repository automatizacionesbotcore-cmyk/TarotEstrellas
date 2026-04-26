<?php

namespace App\Mail;

use App\Models\Cita;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CitaReservadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Cita $cita) {}

    public function build(): self
    {
        return $this
            ->subject('Reserva confirmada — TarotEstrellas')
            ->view('emails.cita-reservada', ['cita' => $this->cita]);
    }
}
