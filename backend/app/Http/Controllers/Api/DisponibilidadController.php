<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BloqueoAgenda;
use App\Models\Cita;
use App\Models\DisponibilidadBase;
use App\Models\TipoConsulta;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisponibilidadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipo_consulta_slug' => ['required', 'string', 'exists:tipos_consulta,slug'],
            'date'               => ['required', 'date_format:Y-m-d'],
            'tz'                 => ['nullable', 'timezone'],
            'especialista_id'    => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $tipo = TipoConsulta::query()
            ->where('slug', $validated['tipo_consulta_slug'])
            ->where('activo', true)
            ->firstOrFail();

        $tz = $validated['tz'] ?? 'America/Santiago';
        $especialista = $this->resolveEspecialista($validated['especialista_id'] ?? null);
        $slots = $this->buildAvailableSlotsForDate($tipo, $validated['date'], $tz, $especialista);

        return response()->json([
            'data' => $slots,
            'meta' => [
                'tipo_consulta_slug'  => $tipo->slug,
                'date'                => $validated['date'],
                'tz'                  => $tz,
                'count'               => count($slots),
                'especialista_id'     => $especialista?->id,
            ],
        ]);
    }

    public function quickBySlug(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'tz'              => ['nullable', 'timezone'],
            'especialista_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $tipo = TipoConsulta::query()
            ->where('slug', $slug)
            ->where('activo', true)
            ->firstOrFail();

        $tz = $request->string('tz')->toString() ?: 'America/Santiago';
        $todayChile = CarbonImmutable::now('America/Santiago')->startOfDay();
        $especialista = $this->resolveEspecialista($request->integer('especialista_id') ?: null);
        $preview = [];

        for ($offset = 0; $offset < 14 && count($preview) < 3; $offset++) {
            $date = $todayChile->addDays($offset)->format('Y-m-d');
            $slots = $this->buildAvailableSlotsForDate($tipo, $date, $tz, $especialista);

            foreach ($slots as $slot) {
                $preview[] = $slot;
                if (count($preview) >= 3) {
                    break;
                }
            }
        }

        return response()->json([
            'data' => $preview,
            'meta' => [
                'tipo_consulta_slug' => $slug,
                'count' => count($preview),
            ],
        ]);
    }

    private function resolveEspecialista(?int $id): ?User
    {
        if ($id) {
            return User::query()
                ->whereHas('roles', fn ($q) => $q->where('nombre', 'admin_especialista'))
                ->find($id);
        }

        // Fallback: primer especialista activo según perfil_especialista.orden_display
        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('nombre', 'admin_especialista'))
            ->whereHas('perfilEspecialista', fn ($q) => $q->where('activo', true))
            ->join('perfil_especialista', 'perfil_especialista.user_id', '=', 'users.id')
            ->orderBy('perfil_especialista.orden_display')
            ->select('users.*')
            ->first()
            ?? User::query()
                ->whereHas('roles', fn ($q) => $q->where('nombre', 'admin_especialista'))
                ->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildAvailableSlotsForDate(TipoConsulta $tipo, string $date, string $tz, ?User $especialista): array
    {
        $duration = (int) $tipo->duracion_minutos;

        $clientDayStartUtc = CarbonImmutable::parse($date, $tz)->startOfDay()->setTimezone('UTC');
        $clientDayEndUtc = CarbonImmutable::parse($date, $tz)->endOfDay()->setTimezone('UTC');
        $chileDates = collect([
            $clientDayStartUtc->setTimezone('America/Santiago')->format('Y-m-d'),
            $clientDayEndUtc->setTimezone('America/Santiago')->format('Y-m-d'),
        ])->unique()->values()->all();

        $bookedRanges = Cita::query()
            ->whereIn('estado', ['pendiente_abono', 'reservada', 'confirmada', 'en_curso'])
            ->where('inicio_utc', '<', $clientDayEndUtc)
            ->where('fin_utc', '>', $clientDayStartUtc)
            ->get(['inicio_utc', 'fin_utc'])
            ->map(fn ($c) => [
                'start' => CarbonImmutable::parse($c->inicio_utc),
                'end' => CarbonImmutable::parse($c->fin_utc),
            ])
            ->toArray();

        // Minimum anticipation: keep same-day bookings closed, but allow tomorrow's agenda.
        $minStart = CarbonImmutable::now($tz)->addDay()->startOfDay()->setTimezone('UTC');

        $slots = [];

        foreach ($chileDates as $chileDate) {
            // Availability is configured by the specialist in Chile time.
            $dateChile = CarbonImmutable::parse($chileDate, 'America/Santiago');
            $diaSemana = $dateChile->dayOfWeek;
            $windows = $this->getAvailabilityWindows($chileDate, $diaSemana, $especialista);

            foreach ($windows as ['start' => $windowStart, 'end' => $windowEnd]) {
                $cursor = $windowStart;

                while ($cursor->addMinutes($duration)->lte($windowEnd)) {
                    $startUtc = $cursor;
                    $endUtc = $cursor->addMinutes($duration);
                    $localStart = $startUtc->setTimezone($tz);

                    // Skip slots outside the client-selected calendar day.
                    if ($localStart->format('Y-m-d') !== $date) {
                        $cursor = $cursor->addMinutes($duration);
                        continue;
                    }

                    // Skip slots before the earliest bookable client day.
                    if ($startUtc->lt($minStart)) {
                        $cursor = $cursor->addMinutes($duration);
                        continue;
                    }

                    // Check if overlaps with any booked range
                    $isTaken = false;
                    foreach ($bookedRanges as $range) {
                        if ($startUtc->lt($range['end']) && $endUtc->gt($range['start'])) {
                            $isTaken = true;
                            break;
                        }
                    }

                    if (! $isTaken) {
                        $localEnd = $endUtc->setTimezone($tz);

                        $slots[] = [
                            'inicio_utc' => $startUtc->toIso8601String(),
                            'fin_utc' => $endUtc->toIso8601String(),
                            'inicio_local' => $localStart->format('Y-m-d H:i:s'),
                            'fin_local' => $localEnd->format('Y-m-d H:i:s'),
                            'zona_horaria' => $tz,
                        ];
                    }

                    $cursor = $cursor->addMinutes($duration);
                }
            }
        }

        usort($slots, fn (array $a, array $b) => strcmp($a['inicio_utc'], $b['inicio_utc']));

        return $slots;
    }

    /**
     * Returns availability windows in UTC for the given date.
     * Reads from disponibilidad_base and applies bloqueos_agenda.
     *
     * @return array<int, array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    private function getAvailabilityWindows(string $date, int $diaSemana, ?User $especialista): array
    {
        // If no DB config exists yet, fall back to hardcoded 10-18
        if (! $especialista) {
            return $this->fallbackWindow($date);
        }

        $horarios = DisponibilidadBase::query()
            ->where('especialista_id', $especialista->id)
            ->where('dia_semana', $diaSemana)
            ->get();

        if ($horarios->isEmpty()) {
            return $this->fallbackWindow($date);
        }

        $horariosActivos = $horarios->where('activo', true);

        if ($horariosActivos->isEmpty()) {
            return [];
        }

        $windows = $horariosActivos->map(function ($h) use ($date) {
            $start = CarbonImmutable::parse($date.' '.$h->hora_inicio, 'America/Santiago')->setTimezone('UTC');
            $end = CarbonImmutable::parse($date.' '.$h->hora_fin, 'America/Santiago')->setTimezone('UTC');
            return ['start' => $start, 'end' => $end];
        })->toArray();

        // Apply bloqueos (subtract blocked ranges)
        if ($especialista) {
            $dayStartUtc = CarbonImmutable::parse($date, 'America/Santiago')->startOfDay()->setTimezone('UTC');
            $dayEndUtc = CarbonImmutable::parse($date, 'America/Santiago')->endOfDay()->setTimezone('UTC');

            $bloqueos = BloqueoAgenda::query()
                ->where('especialista_id', $especialista->id)
                ->where('tipo', 'bloqueo')
                ->where('fecha_inicio_utc', '<', $dayEndUtc)
                ->where('fecha_fin_utc', '>', $dayStartUtc)
                ->get();

            foreach ($bloqueos as $bloqueo) {
                $bStart = CarbonImmutable::parse($bloqueo->fecha_inicio_utc);
                $bEnd = CarbonImmutable::parse($bloqueo->fecha_fin_utc);
                $windows = $this->subtractRange($windows, $bStart, $bEnd);
            }

            // Apply apertura_extra (add extra windows)
            $extras = BloqueoAgenda::query()
                ->where('especialista_id', $especialista->id)
                ->where('tipo', 'apertura_extra')
                ->where('fecha_inicio_utc', '<', $dayEndUtc)
                ->where('fecha_fin_utc', '>', $dayStartUtc)
                ->get();

            foreach ($extras as $extra) {
                $windows[] = [
                    'start' => CarbonImmutable::parse($extra->fecha_inicio_utc),
                    'end' => CarbonImmutable::parse($extra->fecha_fin_utc),
                ];
            }
        }

        return array_filter($windows, fn ($w) => $w['start']->lt($w['end']));
    }

    /**
     * @param array<int, array{start: CarbonImmutable, end: CarbonImmutable}> $windows
     * @return array<int, array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    private function subtractRange(array $windows, CarbonImmutable $bStart, CarbonImmutable $bEnd): array
    {
        $result = [];
        foreach ($windows as $w) {
            if ($bEnd->lte($w['start']) || $bStart->gte($w['end'])) {
                // No overlap
                $result[] = $w;
            } elseif ($bStart->lte($w['start']) && $bEnd->gte($w['end'])) {
                // Block covers whole window — remove it
            } elseif ($bStart->gt($w['start']) && $bEnd->lt($w['end'])) {
                // Block is in the middle — split
                $result[] = ['start' => $w['start'], 'end' => $bStart];
                $result[] = ['start' => $bEnd, 'end' => $w['end']];
            } elseif ($bStart->lte($w['start'])) {
                $result[] = ['start' => $bEnd, 'end' => $w['end']];
            } else {
                $result[] = ['start' => $w['start'], 'end' => $bStart];
            }
        }
        return $result;
    }

    /**
     * @return array<int, array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    private function fallbackWindow(string $date): array
    {
        return [[
            'start' => CarbonImmutable::parse($date.' 10:00:00', 'America/Santiago')->setTimezone('UTC'),
            'end' => CarbonImmutable::parse($date.' 18:00:00', 'America/Santiago')->setTimezone('UTC'),
        ]];
    }
}
