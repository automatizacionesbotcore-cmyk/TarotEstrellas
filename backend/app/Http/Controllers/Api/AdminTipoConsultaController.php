<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TipoConsulta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminTipoConsultaController extends Controller
{
    public function index(): JsonResponse
    {
        $tipos = TipoConsulta::query()
            ->orderBy('orden_visualizacion')
            ->orderBy('nombre')
            ->get();

        return response()->json(['data' => $tipos]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre'                      => 'required|string|max:100|unique:tipos_consulta,nombre',
            'descripcion'                 => 'required|string|max:500',
            'duracion_minutos'            => 'required|integer|min:15|max:240',
            'precio_referencial_centavos' => 'required|integer|min:0',
            'moneda'                      => 'required|string|in:CLP,USD',
            'color_hex'                   => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'requiere_datos_natales'      => 'boolean',
        ]);

        $validated['slug'] = Str::slug($validated['nombre']);
        $validated['orden_visualizacion'] = (TipoConsulta::max('orden_visualizacion') ?? 0) + 1;
        $validated['activo'] = true;

        $tipo = TipoConsulta::create($validated);

        return response()->json(['data' => $tipo], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tipo = TipoConsulta::findOrFail($id);

        $validated = $request->validate([
            'nombre'                      => "required|string|max:100|unique:tipos_consulta,nombre,{$id}",
            'descripcion'                 => 'required|string|max:500',
            'duracion_minutos'            => 'required|integer|min:15|max:240',
            'precio_referencial_centavos' => 'required|integer|min:0',
            'moneda'                      => 'required|string|in:CLP,USD',
            'color_hex'                   => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'requiere_datos_natales'      => 'boolean',
        ]);

        $tipo->update($validated);

        return response()->json(['data' => $tipo->fresh()]);
    }
}
