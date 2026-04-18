<?php

namespace App\Jobs;

use App\Models\Cita;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpirarReservasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Cita::query()
            ->where('estado', 'pendiente_abono')
            ->whereNotNull('reservada_hasta')
            ->where('reservada_hasta', '<=', now())
            ->update([
                'estado' => 'expirada',
                'cancelada_en' => now(),
                'motivo_cancelacion' => 'Expirada por falta de abono dentro de la ventana de tiempo.',
                'updated_at' => now(),
            ]);
    }
}
