<?php

namespace App\Jobs;

use App\Models\CreditoCliente;
use App\Models\Membresia;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExpirarMembresiasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $vencidas = Membresia::query()
            ->where('estado', 'activa')
            ->whereDate('fecha_fin', '<', now()->toDateString())
            ->get();

        foreach ($vencidas as $m) {
            $m->update(['estado' => 'expirada']);
            Log::info('Membresia expirada', ['membresia_uuid' => $m->uuid, 'cliente_id' => $m->cliente_id]);
        }

        // Expirar creditos vencidos
        CreditoCliente::query()
            ->where('estado', 'disponible')
            ->whereNotNull('vigente_hasta')
            ->where('vigente_hasta', '<', now())
            ->update(['estado' => 'expirado', 'updated_at' => now()]);
    }
}
