<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EspecialistaDashboardController extends Controller
{
    public function misCitas(Request $request): JsonResponse
    {
        $user = $request->user();

        $roles = $user->roles->pluck('nombre')->toArray();
        if (! in_array('admin_especialista', $roles, true) && ! in_array('super_admin', $roles, true)) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $citas = Cita::query()
            ->where('especialista_id', $user->id)
            ->whereIn('estado', ['reservada', 'confirmada', 'finalizada'])
            ->with([
                'cliente:id,email',
                'cliente.profile:user_id,nombre',
                'tipoConsulta:id,nombre,duracion_minutos',
            ])
            ->orderBy('inicio_utc', 'asc')
            ->get()
            ->map(fn (Cita $c) => [
                'uuid' => $c->uuid,
                'estado' => $c->estado,
                'inicio_utc' => $c->inicio_utc,
                'duracion_minutos' => $c->duracion_minutos,
                'servicio' => $c->tipoConsulta?->nombre,
                'cliente_nombre' => $c->cliente?->profile?->nombre ?? $c->cliente?->email,
            ]);

        return response()->json(['data' => $citas]);
    }
}
