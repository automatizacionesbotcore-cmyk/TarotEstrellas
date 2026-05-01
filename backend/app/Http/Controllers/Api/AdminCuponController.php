<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminCuponController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Cupon::query();

        if ($search = $request->query('q')) {
            $q->where(function ($w) use ($search) {
                $w->where('codigo', 'like', '%' . $search . '%')
                  ->orWhere('descripcion', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('activo')) {
            $q->where('activo', filter_var($request->query('activo'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->boolean('vigentes')) {
            $now = now();
            $q->where('activo', true)
              ->where('vigente_desde', '<=', $now)
              ->where(function ($w) use ($now) {
                  $w->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $now);
              });
        }

        $perPage = min((int) $request->query('per_page', 25), 100);
        $items = $q->orderByDesc('id')->paginate($perPage);

        return response()->json($items);
    }

    public function show(string $codigo): JsonResponse
    {
        $cupon = Cupon::where('codigo', strtoupper($codigo))->firstOrFail();
        return response()->json(['data' => $cupon]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge(['codigo' => strtoupper((string) $request->input('codigo'))]);
        $data = $this->validatePayload($request);

        $cupon = Cupon::create($data);

        return response()->json(['data' => $cupon], 201);
    }

    public function update(Request $request, string $codigo): JsonResponse
    {
        $cupon = Cupon::where('codigo', strtoupper($codigo))->firstOrFail();
        if ($request->has('codigo')) {
            $request->merge(['codigo' => strtoupper((string) $request->input('codigo'))]);
        }
        $data = $this->validatePayload($request, $cupon->id);

        $cupon->update($data);

        return response()->json(['data' => $cupon->fresh()]);
    }

    public function destroy(string $codigo): JsonResponse
    {
        $cupon = Cupon::where('codigo', strtoupper($codigo))->firstOrFail();
        $cupon->delete();

        return response()->json(['message' => 'Cupón eliminado.']);
    }

    public function toggle(string $codigo): JsonResponse
    {
        $cupon = Cupon::where('codigo', strtoupper($codigo))->firstOrFail();
        $cupon->activo = ! $cupon->activo;
        $cupon->save();

        return response()->json(['data' => $cupon]);
    }

    private function validatePayload(Request $request, ?int $cuponId = null): array
    {
        return $request->validate([
            'codigo'                  => ['required', 'string', 'max:30',
                Rule::unique('cupones', 'codigo')->ignore($cuponId),
            ],
            'descripcion'             => ['nullable', 'string', 'max:255'],
            'tipo_descuento'          => ['required', Rule::in(['porcentaje', 'monto_fijo'])],
            'valor_descuento'         => ['required', 'numeric', 'min:0'],
            'moneda'                  => ['nullable', 'string', 'size:3'],
            'uso_maximo_total'        => ['nullable', 'integer', 'min:1'],
            'uso_maximo_por_cliente'  => ['nullable', 'integer', 'min:1'],
            'vigente_desde'           => ['required', 'date'],
            'vigente_hasta'           => ['nullable', 'date', 'after:vigente_desde'],
            'monto_minimo_centavos'   => ['nullable', 'integer', 'min:0'],
            'solo_primera_consulta'   => ['boolean'],
            'activo'                  => ['boolean'],
        ]);
    }
}
