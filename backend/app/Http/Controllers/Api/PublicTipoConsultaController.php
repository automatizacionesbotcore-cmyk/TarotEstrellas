<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TipoConsulta;
use Illuminate\Http\JsonResponse;

class PublicTipoConsultaController extends Controller
{
    public function index(): JsonResponse
    {
        $tipos = TipoConsulta::query()
            ->where('activo', true)
            ->orderBy('orden_visualizacion')
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'data' => $tipos,
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $tipo = TipoConsulta::query()
            ->where('slug', $slug)
            ->where('activo', true)
            ->firstOrFail();

        return response()->json([
            'data' => $tipo,
        ]);
    }
}
