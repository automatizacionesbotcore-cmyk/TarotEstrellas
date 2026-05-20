<?php

namespace App\Mail;

use App\Mail\Concerns\TaggedMail;
use App\Models\Cita;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class CitaReprogramadaMail extends Mailable
{
    use Queueable, SerializesModels, TaggedMail;

    public function __construct(
        public Cita $cita,
        public ?string $inicioAnterior = null,
        public ?string $motivo = null,
    ) {}

    public function build(): self
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', 'https://tarotestrellas.com'), '/');
        $aceptarUrl = URL::temporarySignedRoute(
            'citas.aceptar-reprogramacion',
            now()->addDays(14),
            ['uuid' => $this->cita->uuid]
        );

        return $this
            ->subject('Tu cita fue reprogramada - TarotEstrellas')
            ->tagNotif('cita_reprogramada', $this->cita->cliente_id, $this->cita->id)
            ->view('emails.cita-reprogramada', [
                'cita' => $this->cita,
                'inicioAnterior' => $this->inicioAnterior,
                'motivo' => $this->motivo,
                'aceptarUrl' => $aceptarUrl,
                'reagendarUrl' => $frontendUrl . '/app/citas/' . $this->cita->uuid . '?reagendar=1',
            ]);
    }
}
