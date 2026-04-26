<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TipoConsulta;
use App\Models\TipoConsultaPrecio;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTipoConsultaPrecioController extends Controller
{
    public function index(int $tipoConsultaId): JsonResponse
    {
        $tipo = TipoConsulta::findOrFail($tipoConsultaId);

        $precios = TipoConsultaPrecio::query()
            ->where('tipo_consulta_id', $tipo->id)
            ->orderBy('moneda')
            ->orderByDesc('vigente_desde')
            ->get();

        return response()->json(['data' => $precios]);
    }

    public function upsert(Request $request, int $tipoConsultaId): JsonResponse
    {
        $tipo = TipoConsulta::findOrFail($tipoConsultaId);

        $validated = $request->validate([
            'precios' => ['required', 'array'],
            'precios.*.moneda' => ['required', 'string', 'size:3'],
            'precios.*.precio_centavos' => ['required', 'integer', 'min:0'],
        ]);

        $today = Carbon::today()->format('Y-m-d');

        foreach ($validated['precios'] as $p) {
            // Expire the current active price
            TipoConsultaPrecio::query()
                ->where('tipo_consulta_id', $tipo->id)
                ->where('moneda', $p['moneda'])
                ->whereNull('vigente_hasta')
                ->update(['vigente_hasta' => $today]);

            // Create new active price
            TipoConsultaPrecio::query()->create([
                'tipo_consulta_id' => $tipo->id,
                'moneda' => $p['moneda'],
                'precio_centavos' => $p['precio_centavos'],
                'vigente_desde' => $today,
                'vigente_hasta' => null,
            ]);
        }

        return response()->json(['message' => 'Precios actualizados']);
    }
}
