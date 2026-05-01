<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Resena;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminResenaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Resena::query()->with(['cliente.profile:user_id,nombre,apellido', 'especialista:id,name', 'cita:id,uuid,codigo_referencia']);

        if ($request->filled('especialista_id')) {
            $q->where('especialista_id', (int) $request->query('especialista_id'));
        }

        if ($request->filled('puntuacion_max')) {
            $q->where('puntuacion', '<=', (int) $request->query('puntuacion_max'));
        }

        if ($request->filled('puntuacion_min')) {
            $q->where('puntuacion', '>=', (int) $request->query('puntuacion_min'));
        }

        if ($request->filled('visible')) {
            $q->where('visible', filter_var($request->query('visible'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->boolean('sin_responder')) {
            $q->whereNull('respuesta_admin');
        }

        if ($search = $request->query('q')) {
            $q->where('comentario', 'like', '%' . $search . '%');
        }

        $perPage = min((int) $request->query('per_page', 25), 100);
        $items = $q->latest()->paginate($perPage);

        return response()->json($items);
    }

    public function show(string $uuid): JsonResponse
    {
        $resena = Resena::with(['cliente.profile:user_id,nombre,apellido', 'especialista:id,name', 'cita:id,uuid,codigo_referencia,inicio_utc'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return response()->json(['data' => $resena]);
    }

    public function responder(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'respuesta' => ['required', 'string', 'max:2000'],
        ]);

        $resena = Resena::where('uuid', $uuid)->firstOrFail();
        $resena->update([
            'respuesta_admin' => $data['respuesta'],
            'respondida_en'   => now(),
            'respondida_por'  => $request->user()->id,
        ]);

        return response()->json(['data' => $resena->fresh()]);
    }

    public function eliminarRespuesta(string $uuid): JsonResponse
    {
        $resena = Resena::where('uuid', $uuid)->firstOrFail();
        $resena->update([
            'respuesta_admin' => null,
            'respondida_en'   => null,
            'respondida_por'  => null,
        ]);

        return response()->json(['data' => $resena->fresh()]);
    }

    public function toggleVisibilidad(string $uuid): JsonResponse
    {
        $resena = Resena::where('uuid', $uuid)->firstOrFail();
        $resena->visible = ! $resena->visible;
        $resena->save();

        return response()->json(['data' => $resena]);
    }
}
