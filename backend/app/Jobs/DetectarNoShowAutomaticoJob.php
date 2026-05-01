<?php

namespace App\Jobs;

use App\Models\Cita;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Marca como candidatas a no-show las citas confirmadas cuya hora fin paso hace mas
 * de N minutos sin haber sido finalizadas ni iniciadas en sala. NO procesa reembolso
 * automaticamente — solo emite log/flag para que el admin las revise via UI.
 */
class DetectarNoShowAutomaticoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $ventanaMinutos = 15) {}

    public function handle(): void
    {
        $umbral = now()->subMinutes($this->ventanaMinutos);

        $candidatas = Cita::query()
            ->where('estado', 'confirmada')
            ->where('fin_utc', '<=', $umbral)
            ->get();

        foreach ($candidatas as $c) {
            Log::warning('Cita candidata a no-show automatica', [
                'cita_uuid'         => $c->uuid,
                'codigo_referencia' => $c->codigo_referencia,
                'fin_utc'           => $c->fin_utc?->toIso8601String(),
                'minutos_pasados'   => now()->diffInMinutes($c->fin_utc),
            ]);
        }
    }
}
