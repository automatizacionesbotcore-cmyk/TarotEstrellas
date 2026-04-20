<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grabacion;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GrabacionController extends Controller
{
    public function obtenerUrlFirmada(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $grabacion = Grabacion::query()
            ->with('cita:id,cliente_id,uuid')
            ->where('uuid', $uuid)
            ->firstOrFail();

        if (! $grabacion->cita || (int) $grabacion->cita->cliente_id !== (int) $user->id) {
            return response()->json([
                'message' => 'No autorizado para acceder a esta grabacion.',
            ], 403);
        }

        if (! $grabacion->url_grabacion) {
            return response()->json([
                'message' => 'La grabacion no tiene URL disponible.',
            ], 404);
        }

        $expiresAt = now()->addHour();
        $separator = str_contains($grabacion->url_grabacion, '?') ? '&' : '?';
        $signedUrl = $grabacion->url_grabacion.$separator.'expires='.urlencode($expiresAt->toIso8601String());

        return response()->json([
            'data' => [
                'grabacion_uuid' => $grabacion->uuid,
                'cita_uuid' => optional($grabacion->cita)->uuid,
                'url' => $signedUrl,
                'expires_at' => $expiresAt->toIso8601String(),
            ],
        ]);
    }

    public function registrarDescarga(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $grabacion = Grabacion::query()
            ->with('cita:id,cliente_id,uuid')
            ->where('uuid', $uuid)
            ->firstOrFail();

        if (! $grabacion->cita || (int) $grabacion->cita->cliente_id !== (int) $user->id) {
            return response()->json([
                'message' => 'No autorizado para registrar descarga de esta grabacion.',
            ], 403);
        }

        $now = now();

        $grabacion->forceFill([
            'descargada_por_cliente' => true,
            'primera_descarga_en' => $grabacion->primera_descarga_en ?: $now,
            'ultima_descarga_en' => $now,
            'total_descargas' => (int) $grabacion->total_descargas + 1,
        ])->save();

        return response()->json([
            'message' => 'Descarga registrada correctamente.',
            'data' => [
                'grabacion_uuid' => $grabacion->uuid,
                'cita_uuid' => optional($grabacion->cita)->uuid,
                'descargada_por_cliente' => (bool) $grabacion->descargada_por_cliente,
                'primera_descarga_en' => optional($grabacion->primera_descarga_en)->toIso8601String(),
                'ultima_descarga_en' => optional($grabacion->ultima_descarga_en)->toIso8601String(),
                'total_descargas' => (int) $grabacion->total_descargas,
            ],
        ]);
    }
}
