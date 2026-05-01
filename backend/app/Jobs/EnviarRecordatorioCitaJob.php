<?php

namespace App\Jobs;

use App\Mail\RecordatorioCitaMail;
use App\Models\Cita;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnviarRecordatorioCitaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $minutosAntes = 1440, public string $canal = 'email')
    {
    }

    public function handle(): void
    {
        // Citas confirmadas que comienzan dentro del rango [-30min, +30min] alrededor del target.
        $target = now()->addMinutes($this->minutosAntes);
        $desde = $target->copy()->subMinutes(30);
        $hasta = $target->copy()->addMinutes(30);

        Cita::query()
            ->where('estado', 'confirmada')
            ->whereBetween('inicio_utc', [$desde, $hasta])
            ->with(['cliente:id,email,telefono', 'cliente.profile:user_id,nombre', 'tipoConsulta:id,nombre,duracion_minutos'])
            ->each(function (Cita $cita) {
                if (! $cita->cliente?->email) {
                    return;
                }

                if ($this->canal === 'whatsapp') {
                    // Stub: integración WhatsApp via WhatsappWebhook/Meta. Se registra log mientras se cablea provider.
                    Log::info('[recordatorio] WhatsApp', [
                        'cita_uuid' => $cita->uuid,
                        'telefono' => $cita->cliente->telefono ?? null,
                        'minutos_antes' => $this->minutosAntes,
                    ]);
                    return;
                }

                Mail::to($cita->cliente->email)->send(new RecordatorioCitaMail($cita, $this->minutosAntes));
            });
    }
}

