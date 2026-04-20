<?php

namespace App\Jobs;

use App\Mail\ResumenListoMail;
use App\Models\Resumen;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class NotificarResumenListoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $resumenId;

    public function __construct(int $resumenId)
    {
        $this->resumenId = $resumenId;
    }

    public function handle(): void
    {
        $resumen = Resumen::query()
            ->with(['cita.cliente'])
            ->find($this->resumenId);

        if (! $resumen || ! $resumen->cita || ! $resumen->cita->cliente) {
            return;
        }

        $cliente = $resumen->cita->cliente;

        if (! is_string($cliente->email) || $cliente->email === '') {
            return;
        }

        Mail::to($cliente->email)->send(new ResumenListoMail($resumen));
    }
}
