<?php

namespace App\Jobs;

use App\Models\Grabacion;
use App\Services\DailyRoomService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class LimpiarGrabacionesExpiradasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(DailyRoomService $daily): void
    {
        $vencidas = Grabacion::query()
            ->whereNotNull('expira_en')
            ->whereNull('borrada_en')
            ->where('expira_en', '<=', now())
            ->limit(100)
            ->get();

        foreach ($vencidas as $grabacion) {
            try {
                if ($grabacion->daily_recording_id) {
                    $daily->eliminarGrabacion($grabacion->daily_recording_id);
                }

                $grabacion->update([
                    'borrada_en' => now(),
                    'url_grabacion' => null,
                    'estado' => 'expirada',
                ]);
            } catch (Throwable $e) {
                Log::warning('LimpiarGrabacionesExpiradas: error al borrar grabacion', [
                    'grabacion_id' => $grabacion->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
