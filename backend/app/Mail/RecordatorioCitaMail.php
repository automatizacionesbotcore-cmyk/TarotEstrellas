<?php

namespace App\Mail;

use App\Models\Cita;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RecordatorioCitaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Cita $cita) {}

    public function build(): self
    {
        return $this
            ->subject('Recordatorio: tu cita es mañana — TarotEstrellas')
            ->view('emails.recordatorio-cita', ['cita' => $this->cita]);
    }
}
