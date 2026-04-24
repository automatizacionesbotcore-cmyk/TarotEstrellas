<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TipoConsulta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicTipoConsultaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TipoConsulta::query()->where('activo', true);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('descripcion', 'like', "%{$search}%");
            });
        }

        if ($duracion = $request->query('duracion')) {
            $query->where('duracion_minutos', '<=', (int) $duracion);
        }

        if ($request->query('requiere_datos_natales') !== null) {
            $val = filter_var($request->query('requiere_datos_natales'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($val !== null) {
                $query->where('requiere_datos_natales', $val);
            }
        }

        if ($min = $request->query('precio_min')) {
            $query->where('precio_referencial_centavos', '>=', (int) $min * 100);
        }

        if ($max = $request->query('precio_max')) {
            $query->where('precio_referencial_centavos', '<=', (int) $max * 100);
        }

        $tipos = $query
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
