<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Paquete;
use App\Models\TipoConsulta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminPaqueteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Paquete::query()->with('tiposConsulta:id,slug,nombre');
        if ($s = $request->string('q')->toString()) {
            $q->where(fn ($w) => $w->where('nombre', 'like', "%$s%")->orWhere('slug', 'like', "%$s%"));
        }
        if ($request->has('activo')) {
            $q->where('activo', $request->boolean('activo'));
        }
        return response()->json($q->orderByDesc('orden_visualizacion')->orderBy('nombre')
            ->paginate((int) $request->integer('per_page', 20)));
    }

    public function show(int $id): JsonResponse
    {
        $p = Paquete::with('tiposConsulta:id,slug,nombre')->findOrFail($id);
        return response()->json(['data' => $p]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->filled('slug') && $request->filled('nombre')) {
            $request->merge(['slug' => Str::slug((string) $request->input('nombre'))]);
        }
        $data = $this->validatePayload($request, null);
        $p = Paquete::create($data);
        $this->syncTipos($p, (array) $request->input('tipos_consulta_ids', []));
        return response()->json(['data' => $p->load('tiposConsulta:id,slug,nombre')], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $p = Paquete::findOrFail($id);
        $data = $this->validatePayload($request, $id);
        $p->update($data);
        if ($request->has('tipos_consulta_ids')) {
            $this->syncTipos($p, (array) $request->input('tipos_consulta_ids'));
        }
        return response()->json(['data' => $p->fresh('tiposConsulta:id,slug,nombre')]);
    }

    public function destroy(int $id): JsonResponse
    {
        Paquete::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    public function toggle(int $id): JsonResponse
    {
        $p = Paquete::findOrFail($id);
        $p->activo = ! $p->activo;
        $p->save();
        return response()->json(['data' => $p]);
    }

    private function validatePayload(Request $request, ?int $id): array
    {
        return $request->validate([
            'slug' => ['required', 'string', 'max:120', Rule::unique('paquetes', 'slug')->ignore($id)],
            'nombre' => ['required', 'string', 'max:191'],
            'descripcion' => ['nullable', 'string'],
            'tipo' => ['required', 'string', 'in:paquete,membresia'],
            'consultas_incluidas' => ['required', 'integer', 'min:1', 'max:120'],
            'vigencia_dias' => ['required', 'integer', 'min:1', 'max:3650'],
            'precio_centavos' => ['required', 'integer', 'min:0'],
            'moneda' => ['required', 'string', 'size:3'],
            'descuento_porcentaje' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'activo' => ['sometimes', 'boolean'],
            'imagen_url' => ['nullable', 'string', 'max:500'],
            'destacado' => ['sometimes', 'boolean'],
            'orden_visualizacion' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'tipos_consulta_ids' => ['sometimes', 'array'],
            'tipos_consulta_ids.*' => ['integer', 'exists:tipos_consulta,id'],
        ]);
    }

    private function syncTipos(Paquete $p, array $ids): void
    {
        $valid = TipoConsulta::whereIn('id', $ids)->pluck('id')->all();
        $p->tiposConsulta()->sync($valid);
    }
}
