<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\User;
use App\Services\DailyRoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class VideollamadaController extends Controller
{
    public function __construct(private readonly DailyRoomService $daily)
    {
    }

    /**
     * Devuelve URL+token para que el participante entre a la sala de la cita.
     * Ventana permitida: 15 min antes de inicio_utc -> 30 min despues de fin_utc.
     */
    public function entrar(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $cita = Cita::query()
            ->with('tipoConsulta:id,nombre,duracion_minutos')
            ->where('uuid', $uuid)
            ->first();
        if (! $cita) {
            return response()->json(['message' => 'Cita no encontrada.'], 404);
        }

        $isCliente = (int) $cita->cliente_id === (int) $user->id;
        $isEspecialista = $cita->especialista_id !== null && (int) $cita->especialista_id === (int) $user->id;
        $isAdmin = method_exists($user, 'isAdmin') ? $user->isAdmin() : false;

        if (! $isCliente && ! $isEspecialista && ! $isAdmin) {
            return response()->json(['message' => 'No autorizado para entrar a esta sala.'], 403);
        }

        $pagoCompletado = in_array($cita->estado_pago, ['pagado', 'aprobado'], true)
            || in_array($cita->estado, ['pagada', 'confirmada', 'en_curso'], true);

        if (! $pagoCompletado) {
            return response()->json([
                'message' => 'Esta consulta aún no tiene el pago completado.',
                'pago_completado' => false,
            ], 409);
        }

        $now = now();
        $abre = $cita->inicio_utc?->copy()->subMinutes(15);
        $cierra = $cita->fin_utc?->copy()->addMinutes(30);

        if ($abre && $now->lt($abre)) {
            return response()->json([
                'message' => 'La sala aun no esta disponible. Abre 15 minutos antes del inicio.',
                'abre_en' => $abre->toIso8601String(),
            ], 409);
        }
        if ($cierra && $now->gt($cierra)) {
            return response()->json([
                'message' => 'La sala ya cerro.',
                'cerro_en' => $cierra->toIso8601String(),
            ], 410);
        }

        try {
            $sala = $this->daily->crearSala($cita);
            $cita->refresh();
            $token = $this->daily->crearMeetingToken($cita, $user, isOwner: $isEspecialista || $isAdmin);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'No fue posible preparar la sala: ' . $e->getMessage(),
            ], 502);
        }

        return response()->json([
            'data' => [
                'cita_uuid' => $cita->uuid,
                'room_url' => $sala['room_url'],
                'room_name' => $sala['room_name'],
                'url' => $sala['room_url'],
                'token' => $token,
                'is_owner' => $isEspecialista || $isAdmin,
                'sala_creada' => true,
                'pago_completado' => true,
                'en_horario' => true,
                'recording_enabled' => (bool) $cita->grabacion_solicitada,
                'requires_recording_consent' => $isCliente && (bool) $cita->grabacion_solicitada,
                'grabacion_habilitada' => (bool) $cita->grabacion_solicitada,
                'cita' => [
                    'uuid' => $cita->uuid,
                    'inicio_utc' => optional($cita->inicio_utc)->toIso8601String(),
                    'fin_utc' => optional($cita->fin_utc)->toIso8601String(),
                    'tipo_consulta' => $cita->tipoConsulta ? [
                        'nombre' => $cita->tipoConsulta->nombre,
                        'duracion_minutos' => (int) $cita->tipoConsulta->duracion_minutos,
                    ] : null,
                ],
                'expira_en' => $cierra?->toIso8601String(),
                'mock' => $sala['mock'] ?? false,
            ],
        ]);
    }

    public function iniciarGrabacion(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $cita = Cita::query()->where('uuid', $uuid)->firstOrFail();

        $isOwner = ($cita->especialista_id !== null && (int) $cita->especialista_id === (int) $user->id)
            || (method_exists($user, 'hasRole') && $user->hasRole('admin'));
        if (! $isOwner) {
            return response()->json(['message' => 'Solo el especialista o admin puede iniciar la grabacion.'], 403);
        }
        if (! $cita->grabacion_solicitada) {
            return response()->json(['message' => 'La grabacion no fue solicitada al reservar.'], 422);
        }
        if (! $cita->daily_room_name) {
            return response()->json(['message' => 'La sala aun no fue creada.'], 409);
        }

        $ok = $this->daily->iniciarGrabacion($cita->daily_room_name);
        return response()->json(['ok' => $ok]);
    }

    public function detenerGrabacion(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $cita = Cita::query()->where('uuid', $uuid)->firstOrFail();

        $isOwner = ($cita->especialista_id !== null && (int) $cita->especialista_id === (int) $user->id)
            || (method_exists($user, 'hasRole') && $user->hasRole('admin'));
        if (! $isOwner) {
            return response()->json(['message' => 'Solo el especialista o admin puede detener la grabacion.'], 403);
        }
        if (! $cita->daily_room_name) {
            return response()->json(['message' => 'La sala aun no fue creada.'], 409);
        }

        $ok = $this->daily->detenerGrabacion($cita->daily_room_name);
        return response()->json(['ok' => $ok]);
    }
}
