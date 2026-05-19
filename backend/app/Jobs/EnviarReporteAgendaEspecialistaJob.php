<?php

namespace App\Jobs;

use App\Mail\AgendaEspecialistaReportMail;
use App\Models\Cita;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class EnviarReporteAgendaEspecialistaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const ACTIVE_STATES = ['pendiente_abono', 'reservada', 'confirmada', 'en_curso'];

    public function __construct(public string $periodo = 'diario')
    {
    }

    public function handle(): void
    {
        $nowChile = CarbonImmutable::now('America/Santiago');
        [$desdeChile, $hastaChile] = $this->periodo === 'semanal'
            ? [$nowChile->startOfWeek(), $nowChile->endOfWeek()]
            : [$nowChile->startOfDay(), $nowChile->endOfDay()];

        $desdeUtc = $desdeChile->setTimezone('UTC')->toDateTimeString();
        $hastaUtc = $hastaChile->setTimezone('UTC')->toDateTimeString();

        User::query()
            ->whereNotNull('email')
            ->whereHas('roles', fn ($q) => $q->where('nombre', 'admin_especialista'))
            ->where(function ($q) {
                $q->whereDoesntHave('perfilEspecialista')
                    ->orWhereHas('perfilEspecialista', fn ($profile) => $profile->where('activo', true));
            })
            ->each(function (User $especialista) use ($desdeChile, $hastaChile, $desdeUtc, $hastaUtc) {
                $citas = Cita::query()
                    ->with([
                        'cliente:id,email,name',
                        'cliente.profile:user_id,nombre,apellido,telefono',
                        'tipoConsulta:id,nombre,duracion_minutos',
                    ])
                    ->where('especialista_id', $especialista->id)
                    ->whereIn('estado', self::ACTIVE_STATES)
                    ->where('inicio_utc', '>=', $desdeUtc)
                    ->where('inicio_utc', '<=', $hastaUtc)
                    ->orderBy('inicio_utc')
                    ->get();

                Mail::to($especialista->email)->send(new AgendaEspecialistaReportMail(
                    especialista: $especialista,
                    citas: $citas,
                    periodo: $this->periodo,
                    desdeChile: $desdeChile->format('d/m/Y H:i'),
                    hastaChile: $hastaChile->format('d/m/Y H:i'),
                ));
            });
    }
}
