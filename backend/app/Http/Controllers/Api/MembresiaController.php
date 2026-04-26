<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Membresia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MembresiaController extends Controller
{
    /**
     * GET /membresia
     * Retorna membresía activa del cliente autenticado.
     */
    public function show(Request $request): JsonResponse
    {
        $membresia = Membresia::with('paquete')
            ->where('cliente_id', $request->user()->id)
            ->where('estado', 'activa')
            ->where('fecha_fin', '>=', now()->toDateString())
            ->first();

        if (! $membresia) {
            return response()->json(['membresia' => null]);
        }

        return response()->json([
            'membresia' => [
                'uuid'                  => $membresia->uuid,
                'estado'                => $membresia->estado,
                'fecha_inicio'          => $membresia->fecha_inicio->toDateString(),
                'fecha_fin'             => $membresia->fecha_fin->toDateString(),
                'consultas_incluidas'   => $membresia->consultas_incluidas,
                'consultas_usadas'      => $membresia->consultas_usadas,
                'consultas_restantes'   => $membresia->consultasRestantes(),
                'paquete'               => [
                    'nombre'  => $membresia->paquete?->nombre,
                    'slug'    => $membresia->paquete?->slug,
                ],
            ],
        ]);
    }

    /**
     * POST /citas/{uuid}/aplicar-membresia
     * Aplica membresía activa a una cita pendiente.
     */
    public function aplicar(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        $cita = Cita::where('uuid', $uuid)
            ->where('cliente_id', $user->id)
            ->whereIn('estado', ['pendiente_abono', 'reservada'])
            ->firstOrFail();

        $membresia = Membresia::where('cliente_id', $user->id)
            ->where('estado', 'activa')
            ->where('fecha_fin', '>=', now()->toDateString())
            ->first();

        if (! $membresia) {
            return response()->json(['message' => 'No tienes una membresía activa.'], 422);
        }

        if ($membresia->consultasRestantes() <= 0) {
            return response()->json(['message' => 'Tu membresía no tiene consultas disponibles.'], 422);
        }

        if ($cita->membresia_id) {
            return response()->json(['message' => 'Esta cita ya tiene una membresía aplicada.'], 422);
        }

        DB::transaction(function () use ($cita, $membresia) {
            $cita->update([
                'membresia_id'           => $membresia->uuid,
                'precio_final_centavos'  => 0,
            ]);
            $membresia->increment('consultas_usadas');

            if ($membresia->consultasRestantes() <= 0) {
                $membresia->update(['estado' => 'agotada']);
            }
        });

        $cita->refresh();
        $membresia->refresh();

        return response()->json([
            'message'                 => 'Membresía aplicada. La cita queda cubierta.',
            'precio_final_centavos'   => $cita->precio_final_centavos,
            'consultas_restantes'     => $membresia->consultasRestantes(),
        ]);
    }
}
