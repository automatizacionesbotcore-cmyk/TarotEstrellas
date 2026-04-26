<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PerfilEspecialista;
use App\Models\TipoConsulta;
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
            ->map(fn ($p) => $this->formatEspecialista($p));

        return response()->json(['data' => $especialistas]);
    }

    public function show(string $slug): JsonResponse
    {
        $perfil = PerfilEspecialista::query()
            ->where('slug', $slug)
            ->where('activo', true)
            ->with(['user:id,uuid', 'user.profile:user_id,nombre,apellido,biografia,avatar_url'])
            ->firstOrFail();

        // Servicios activos de la plataforma (todos los especialistas comparten el catálogo)
        $servicios = TipoConsulta::query()
            ->where('activo', true)
            ->orderBy('orden_visualizacion')
            ->get(['id', 'slug', 'nombre', 'descripcion', 'duracion_minutos',
                   'precio_referencial_centavos', 'moneda', 'imagen_url', 'color_hex'])
            ->map(fn ($s) => [
                'id'                          => $s->id,
                'slug'                        => $s->slug,
                'nombre'                      => $s->nombre,
                'descripcion'                 => $s->descripcion,
                'duracion_minutos'            => $s->duracion_minutos,
                'precio_referencial_centavos' => $s->precio_referencial_centavos,
                'moneda'                      => $s->moneda,
                'imagen_url'                  => $s->imagen_url,
                'color_hex'                   => $s->color_hex,
            ]);

        return response()->json([
            'data' => [
                'especialista' => $this->formatEspecialista($perfil),
                'servicios'    => $servicios,
            ],
        ]);
    }

    private function formatEspecialista(PerfilEspecialista $p): array
    {
        return [
            'id'           => $p->user_id,
            'uuid'         => $p->user->uuid,
            'slug'         => $p->slug,
            'nombre'       => trim(($p->user->profile->nombre ?? '') . ' ' . ($p->user->profile->apellido ?? '')),
            'especialidad' => $p->especialidad,
            'biografia'    => $p->user->profile->biografia ?? null,
            'avatar_url'   => $p->user->profile->avatar_url ?? null,
            'orden'        => $p->orden_display,
        ];
    }
}
