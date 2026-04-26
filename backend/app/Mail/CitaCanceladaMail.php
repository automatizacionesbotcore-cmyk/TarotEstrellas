<?php

namespace App\Mail;

use App\Models\Cita;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CitaCanceladaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Cita $cita, public bool $conReembolso = false) {}

    public function build(): self
    {
        return $this
            ->subject('Cita cancelada — TarotEstrellas')
            ->view('emails.cita-cancelada', [
                'cita'         => $this->cita,
                'conReembolso' => $this->conReembolso,
            ]);
    }
}
