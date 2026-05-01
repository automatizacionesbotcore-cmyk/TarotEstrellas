<?php

namespace App\Jobs;

use App\Models\Grabacion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AvisarEliminacionProximaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $diasAntes = 7)
    {
    }

    public function handle(): void
    {
        $desde = now()->addDays($this->diasAntes)->startOfDay();
        $hasta = now()->addDays($this->diasAntes)->endOfDay();

        Grabacion::query()
            ->whereNull('borrada_en')
            ->whereBetween('expira_en', [$desde, $hasta])
            ->with(['cita:id,uuid,cliente_id', 'cita.cliente:id,email,telefono'])
            ->each(function (Grabacion $g) {
                $email = $g->cita?->cliente?->email;
                Log::info('[grabacion] aviso eliminacion proxima', [
                    'grabacion_uuid' => $g->uuid,
                    'cita_uuid' => $g->cita?->uuid,
                    'email' => $email,
                    'expira_en' => optional($g->expira_en)->toIso8601String(),
                    'dias_antes' => $this->diasAntes,
                ]);
                // Cuando exista el mailable: Mail::to($email)->send(new GrabacionPorEliminarMail($g));
            });
    }
}
