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
            ->withSymfonyMessage(function ($message) {
                $message->getHeaders()->addTextHeader('X-Notif-Tipo', 'recordatorio_cita');
                $message->getHeaders()->addTextHeader('X-Notif-Plantilla', 'recordatorio_cita');
                if ($this->cita->cliente_id) {
                    $message->getHeaders()->addTextHeader('X-Notif-User-Id', (string) $this->cita->cliente_id);
                }
                $message->getHeaders()->addTextHeader('X-Notif-Cita-Id', (string) $this->cita->id);
                $message->getHeaders()->addTextHeader('X-Notif-Minutos-Antes', (string) $this->minutosAntes);
            })
            ->view('emails.recordatorio-cita', [
                'cita' => $this->cita,
                'cuando' => $cuando,
                'confirmUrl' => $confirmUrl,
                'saldoUrl' => rtrim((string) config('app.frontend_url', 'https://tarotestrellas.com'), '/') . '/app/citas/' . $this->cita->uuid . '/pagar-saldo',
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
