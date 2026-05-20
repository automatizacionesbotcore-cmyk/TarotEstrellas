<?php

namespace App\Jobs;

use App\Mail\RecordatorioCitaMail;
use App\Models\Cita;
use App\Models\NotificacionEnviada;
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
            ->whereIn('estado', ['reservada', 'confirmada'])
            ->whereBetween('inicio_utc', [$desde, $hasta])
            ->with(['cliente:id,email,telefono', 'cliente.profile:user_id,nombre', 'tipoConsulta:id,nombre,duracion_minutos'])
            ->each(function (Cita $cita) {
                if (! $cita->cliente?->email) {
                    return;
                }

                if ($this->alreadySent($cita)) {
                    return;
                }

                if ($this->canal === 'whatsapp') {
                    // Stub: integración WhatsApp via WhatsappWebhook/Meta. Se registra log mientras se cablea provider.
                    Log::info('[recordatorio] WhatsApp', [
                        'cita_uuid' => $cita->uuid,
                        'telefono' => $cita->cliente->telefono ?? null,
                        'minutos_antes' => $this->minutosAntes,
                    ]);

                    \App\Models\NotificacionEnviada::create([
                        'uuid'         => (string) \Illuminate\Support\Str::uuid(),
                        'user_id'      => $cita->cliente_id,
                        'canal'        => 'whatsapp',
                        'tipo'         => 'recordatorio_cita',
                        'destinatario' => $cita->cliente->telefono,
                        'asunto'       => null,
                        'preview'      => "Recordatorio: tu cita en {$this->minutosAntes} min",
                        'estado'       => 'enviado',
                        'proveedor'    => 'stub',
                        'metadata'     => ['cita_id' => $cita->id, 'minutos_antes' => $this->minutosAntes],
                        'enviado_en'   => now(),
                    ]);
                    return;
                }

                Mail::to($cita->cliente->email)->send(new RecordatorioCitaMail($cita, $this->minutosAntes));
            });
    }

    private function alreadySent(Cita $cita): bool
    {
        return NotificacionEnviada::query()
            ->where('canal', $this->canal)
            ->where('tipo', 'recordatorio_cita')
            ->where('metadata->cita_id', (string) $cita->id)
            ->where('metadata->minutos_antes', (string) $this->minutosAntes)
            ->exists();
    }
}
