<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\PerfilEspecialista;
use App\Models\Resena;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ResenaController extends Controller
{
    public function store(Request $request, string $uuid): JsonResponse
    {
        $cita = Cita::query()->where('uuid', $uuid)->firstOrFail();

        if ($cita->cliente_id !== $request->user()->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        if ($cita->estado !== 'finalizada') {
            return response()->json(['message' => 'Solo puedes reseñar citas finalizadas.'], 422);
        }

        if (Resena::query()->where('cita_id', $cita->id)->exists()) {
            return response()->json(['message' => 'Ya dejaste una reseña para esta cita.'], 422);
        }

        $validated = $request->validate([
            'puntuacion' => 'required|integer|min:1|max:5',
            'comentario' => 'nullable|string|max:1000',
        ]);

        $resena = Resena::query()->create([
            'uuid' => (string) Str::uuid(),
            'cita_id' => $cita->id,
            'cliente_id' => $cita->cliente_id,
            'especialista_id' => $cita->especialista_id,
            'puntuacion' => $validated['puntuacion'],
            'comentario' => $validated['comentario'] ?? null,
            'visible' => true,
        ]);

        return response()->json(['data' => $resena], 201);
    }

    public function indexPublic(string $slug): JsonResponse
    {
        $perfil = PerfilEspecialista::query()->where('slug', $slug)->where('activo', true)->firstOrFail();

        $resenas = Resena::query()
            ->where('especialista_id', $perfil->user_id)
            ->where('visible', true)
            ->with(['cliente.profile:user_id,nombre'])
            ->latest()
            ->take(20)
            ->get()
            ->map(fn (Resena $r) => [
                'uuid' => $r->uuid,
                'puntuacion' => $r->puntuacion,
                'comentario' => $r->comentario,
                'cliente_nombre' => $r->cliente?->profile?->nombre ?? 'Anónimo',
                'created_at' => $r->created_at?->toDateString(),
            ]);

        $promedio = Resena::query()
            ->where('especialista_id', $perfil->user_id)
            ->where('visible', true)
            ->avg('puntuacion');

        $total = Resena::query()
            ->where('especialista_id', $perfil->user_id)
            ->where('visible', true)
            ->count();

        return response()->json([
            'data' => [
                'promedio' => $promedio ? round((float) $promedio, 1) : null,
                'total' => $total,
                'resenas' => $resenas,
            ],
        ]);
    }

    public function miResena(string $uuid, Request $request): JsonResponse
    {
        $cita = Cita::query()->where('uuid', $uuid)->firstOrFail();

        if ($cita->cliente_id !== $request->user()->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $resena = Resena::query()->where('cita_id', $cita->id)->first();

        return response()->json(['data' => $resena]);
    }
}
