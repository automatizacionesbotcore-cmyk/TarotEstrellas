<?php

namespace App\Jobs;

use App\Mail\RecordatorioCitaMail;
use App\Models\Cita;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class EnviarRecordatorioCitaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Citas confirmadas que comienzan entre 23 y 25 horas desde ahora
        $desde = now()->addHours(23);
        $hasta = now()->addHours(25);

        Cita::query()
            ->where('estado', 'confirmada')
            ->whereBetween('inicio_utc', [$desde, $hasta])
            ->with(['cliente:id,email', 'cliente.profile:user_id,nombre', 'tipoConsulta:id,nombre,duracion_minutos'])
            ->each(function (Cita $cita) {
                if ($cita->cliente?->email) {
                    Mail::to($cita->cliente->email)->send(new RecordatorioCitaMail($cita));
                }
            });
    }
}
