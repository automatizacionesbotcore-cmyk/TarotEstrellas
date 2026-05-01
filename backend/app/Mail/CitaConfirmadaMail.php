<?php

namespace App\Mail;

use App\Models\Cita;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CitaConfirmadaMail extends Mailable
{
    use Queueable, SerializesModels, \App\Mail\Concerns\TaggedMail;

    public function __construct(public Cita $cita) {}

    public function build(): self
    {
        return $this
            ->subject('Cita confirmada — TarotEstrellas')
            ->tagNotif('cita_confirmada', $this->cita->cliente_id, $this->cita->id)
            ->view('emails.cita-confirmada', ['cita' => $this->cita]);
    }
}
