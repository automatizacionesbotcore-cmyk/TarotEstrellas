<?php

namespace App\Mail;

use App\Mail\Concerns\TaggedMail;
use App\Models\Cita;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SalaRapidaAbiertaMail extends Mailable
{
    use Queueable, SerializesModels, TaggedMail;

    public function __construct(
        public Cita $cita,
        public ?string $mensaje = null,
    ) {}

    public function build(): self
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', 'https://tarotestrellas.com'), '/');

        return $this
            ->subject('Tu sala de consulta esta abierta - TarotEstrellas')
            ->tagNotif('sala_rapida_abierta', $this->cita->cliente_id, $this->cita->id)
            ->view('emails.sala-rapida-abierta', [
                'cita' => $this->cita,
                'mensaje' => $this->mensaje,
                'salaUrl' => $frontendUrl . '/app/sala/' . $this->cita->uuid,
            ]);
    }
}
