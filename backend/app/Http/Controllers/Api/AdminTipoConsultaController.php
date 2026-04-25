<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TipoConsulta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

    public function toggle(int $id): JsonResponse
    {
        $tipo = TipoConsulta::findOrFail($id);
        $tipo->update(['activo' => ! $tipo->activo]);

        return response()->json(['data' => $tipo->fresh()]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:tipos_consulta,id',
        ]);

        foreach ($validated['ids'] as $position => $id) {
            TipoConsulta::where('id', $id)->update(['orden_visualizacion' => $position + 1]);
        }

        return response()->json(['message' => 'Orden actualizado.']);
    }

    public function uploadImagen(Request $request, int $id): JsonResponse
    {
        $tipo = TipoConsulta::findOrFail($id);

        $request->validate([
            'imagen' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($tipo->imagen_url) {
            $oldPath = str_replace('/storage/', 'public/', $tipo->imagen_url);
            Storage::delete($oldPath);
        }

        $ext = $request->file('imagen')->getClientOriginalExtension();
        $filename = "{$tipo->slug}.{$ext}";
        $request->file('imagen')->storeAs('public/tipos-consulta', $filename);

        $tipo->update(['imagen_url' => "/storage/tipos-consulta/{$filename}"]);

        return response()->json(['data' => $tipo->fresh()]);
    }

    public function deleteImagen(int $id): JsonResponse
    {
        $tipo = TipoConsulta::findOrFail($id);

        if ($tipo->imagen_url) {
            $path = str_replace('/storage/', 'public/', $tipo->imagen_url);
            Storage::delete($path);
            $tipo->update(['imagen_url' => null]);
        }

        return response()->json(['data' => $tipo->fresh()]);
    }
}
