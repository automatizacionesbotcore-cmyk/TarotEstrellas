<?php

namespace App\Mail;

use App\Models\Cita;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class RecordatorioCitaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Cita $cita, public int $minutosAntes = 1440) {}

    public function build(): self
    {
        $cuando = $this->cuando();

        $confirmUrl = URL::temporarySignedRoute(
            'citas.confirmar-asistencia',
            now()->addDays(7),
            ['uuid' => $this->cita->uuid]
        );

        return $this
            ->subject("Recordatorio: tu cita es {$cuando} — TarotEstrellas")
            ->view('emails.recordatorio-cita', [
                'cita' => $this->cita,
                'cuando' => $cuando,
                'confirmUrl' => $confirmUrl,
                'minutosAntes' => $this->minutosAntes,
            ]);
    }

    private function cuando(): string
    {
        return match (true) {
            $this->minutosAntes >= 4320 - 30 && $this->minutosAntes <= 4320 + 30 => 'en 3 días',
            $this->minutosAntes >= 1440 - 30 && $this->minutosAntes <= 1440 + 30 => 'mañana',
            $this->minutosAntes >= 60 - 5  && $this->minutosAntes <= 60 + 30   => 'en 1 hora',
            $this->minutosAntes <= 35                                            => 'muy pronto',
            default                                                              => 'próximamente',
        };
    }
}
