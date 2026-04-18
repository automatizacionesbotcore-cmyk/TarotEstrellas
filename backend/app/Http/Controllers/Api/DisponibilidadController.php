<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\TipoConsulta;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisponibilidadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipo_consulta_slug' => ['required', 'string', 'exists:tipos_consulta,slug'],
            'date' => ['required', 'date_format:Y-m-d'],
            'tz' => ['nullable', 'timezone'],
        ]);

        $tipo = TipoConsulta::query()
            ->where('slug', $validated['tipo_consulta_slug'])
            ->where('activo', true)
            ->firstOrFail();

        $tz = $validated['tz'] ?? 'America/Santiago';
        $slots = $this->buildAvailableSlotsForDate($tipo, $validated['date'], $tz);

        return response()->json([
            'data' => $slots,
            'meta' => [
                'tipo_consulta_slug' => $tipo->slug,
                'date' => $validated['date'],
                'tz' => $tz,
                'count' => count($slots),
            ],
        ]);
    }

    public function quickBySlug(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'tz' => ['nullable', 'timezone'],
        ]);

        $tipo = TipoConsulta::query()
            ->where('slug', $slug)
            ->where('activo', true)
            ->firstOrFail();

        $tz = $request->string('tz')->toString() ?: 'America/Santiago';
        $todayChile = CarbonImmutable::now('America/Santiago')->startOfDay();
        $preview = [];

        for ($offset = 0; $offset < 14 && count($preview) < 3; $offset++) {
            $date = $todayChile->addDays($offset)->format('Y-m-d');
            $slots = $this->buildAvailableSlotsForDate($tipo, $date, $tz);

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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildAvailableSlotsForDate(TipoConsulta $tipo, string $date, string $tz): array
    {
        $duration = (int) $tipo->duracion_minutos;
        $dayStartChile = CarbonImmutable::parse($date.' 10:00:00', 'America/Santiago');
        $dayEndChile = CarbonImmutable::parse($date.' 18:00:00', 'America/Santiago');
        $slots = [];

        for ($cursor = $dayStartChile; $cursor->addMinutes($duration)->lte($dayEndChile); $cursor = $cursor->addMinutes($duration)) {
            $startUtc = $cursor->setTimezone('UTC');
            $endUtc = $startUtc->addMinutes($duration);

            $isTaken = Cita::query()
                ->whereIn('estado', ['pendiente_abono', 'reservada', 'confirmada', 'en_curso'])
                ->where('inicio_utc', '<', $endUtc)
                ->where('fin_utc', '>', $startUtc)
                ->exists();

            if (! $isTaken) {
                $localStart = $startUtc->setTimezone($tz);
                $localEnd = $endUtc->setTimezone($tz);

                $slots[] = [
                    'inicio_utc' => $startUtc->toIso8601String(),
                    'fin_utc' => $endUtc->toIso8601String(),
                    'inicio_local' => $localStart->format('Y-m-d H:i:s'),
                    'fin_local' => $localEnd->format('Y-m-d H:i:s'),
                    'zona_horaria' => $tz,
                ];
            }
        }

        return $slots;
    }
}
