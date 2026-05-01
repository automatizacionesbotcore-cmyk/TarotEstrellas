<?php

namespace App\Jobs;

use App\Models\Membresia;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Detecta membresias que vencen en los proximos N dias y registra un evento.
 * El envio de email/push se maneja desde la integracion de notificaciones.
 */
class AvisarVencimientoMembresiasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $diasAntes = 7) {}

    public function handle(): void
    {
        $objetivo = now()->addDays($this->diasAntes)->toDateString();

        $proximas = Membresia::query()
            ->with('cliente:id,email')
            ->where('estado', 'activa')
            ->whereDate('fecha_fin', $objetivo)
            ->get();

        foreach ($proximas as $m) {
            Log::channel(config('logging.default'))->info('Membresia proxima a vencer', [
                'membresia_uuid' => $m->uuid,
                'cliente_id'     => $m->cliente_id,
                'cliente_email'  => $m->cliente?->email,
                'fecha_fin'      => $m->fecha_fin?->toDateString(),
                'dias_antes'     => $this->diasAntes,
            ]);
        }
    }
}
