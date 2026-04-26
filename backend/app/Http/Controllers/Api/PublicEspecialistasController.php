<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PerfilEspecialista;
use Illuminate\Http\JsonResponse;

class PublicEspecialistasController extends Controller
{
    public function index(): JsonResponse
    {
        $especialistas = PerfilEspecialista::query()
            ->where('activo', true)
            ->orderBy('orden_display')
            ->orderBy('id')
            ->with(['user:id,uuid', 'user.profile:user_id,nombre,apellido,biografia,avatar_url'])
            ->get()
            ->map(fn ($p) => [
                'id'           => $p->user_id,
                'uuid'         => $p->user->uuid,
                'slug'         => $p->slug,
                'nombre'       => trim(($p->user->profile->nombre ?? '') . ' ' . ($p->user->profile->apellido ?? '')),
                'especialidad' => $p->especialidad,
                'biografia'    => $p->user->profile->biografia ?? null,
                'avatar_url'   => $p->user->profile->avatar_url ?? null,
                'orden'        => $p->orden_display,
            ]);

        return response()->json(['data' => $especialistas]);
    }
}
