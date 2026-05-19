<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CuentaBancaria;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class CuentaBancariaController extends Controller
{
    private const MAX_CUENTAS = 3;

    /** GET /api/cuentas-bancarias — cuentas activas públicas (para mostrar al cliente al pagar) */
    public function indexPublico(): JsonResponse
    {
        $cuentas = CuentaBancaria::query()
            ->where('activa', true)
            ->orderBy('orden')
            ->get(['uuid', 'banco', 'tipo_cuenta', 'numero_cuenta', 'nombre_titular', 'rut_titular', 'orden']);

        return response()->json(['data' => $cuentas]);
    }

    /** GET /api/admin/cuentas-bancarias — todas las cuentas del especialista autenticado */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $cuentas = CuentaBancaria::query()
            ->where('user_id', $user->id)
            ->orderBy('orden')
            ->get();

        return response()->json(['data' => $cuentas]);
    }

    /** POST /api/admin/cuentas-bancarias */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $count = CuentaBancaria::query()->where('user_id', $user->id)->count();

        if ($count >= self::MAX_CUENTAS) {
            return response()->json([
                'message' => 'Ya tienes el máximo de 3 cuentas bancarias configuradas.',
            ], 422);
        }

        $validated = $request->validate([
            'banco'          => ['required', 'string', 'max:80'],
            'tipo_cuenta'    => ['required', 'in:corriente,vista,ahorro,rut'],
            'numero_cuenta'  => ['required', 'string', 'max:30'],
            'nombre_titular' => ['required', 'string', 'max:100'],
            'rut_titular'    => ['required', 'string', 'max:12'],
            'activa'         => ['sometimes', 'boolean'],
        ], $this->validationMessages());

        // Asignar el siguiente orden disponible (0, 1, 2)
        $usedOrders = CuentaBancaria::query()
            ->where('user_id', $user->id)
            ->pluck('orden')
            ->toArray();

        $orden = 0;
        while (in_array($orden, $usedOrders, true) && $orden < self::MAX_CUENTAS) {
            $orden++;
        }

        $cuenta = CuentaBancaria::query()->create([
            'uuid'           => (string) Str::uuid(),
            'user_id'        => $user->id,
            'banco'          => $validated['banco'],
            'tipo_cuenta'    => $validated['tipo_cuenta'],
            'numero_cuenta'  => $validated['numero_cuenta'],
            'nombre_titular' => $validated['nombre_titular'],
            'rut_titular'    => $validated['rut_titular'],
            'orden'          => $orden,
            'activa'         => (bool) ($validated['activa'] ?? true),
        ]);

        return response()->json([
            'message' => 'Cuenta bancaria agregada correctamente.',
            'data'    => $cuenta,
        ], 201);
    }

    /** PATCH /api/admin/cuentas-bancarias/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $cuenta = CuentaBancaria::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $validated = $request->validate([
            'banco'          => ['sometimes', 'string', 'max:80'],
            'tipo_cuenta'    => ['sometimes', 'in:corriente,vista,ahorro,rut'],
            'numero_cuenta'  => ['sometimes', 'string', 'max:30'],
            'nombre_titular' => ['sometimes', 'string', 'max:100'],
            'rut_titular'    => ['sometimes', 'string', 'max:12'],
            'activa'         => ['sometimes', 'boolean'],
        ], $this->validationMessages());

        $cuenta->forceFill($validated)->save();

        return response()->json([
            'message' => 'Cuenta bancaria actualizada.',
            'data'    => $cuenta->fresh(),
        ]);
    }

    /** DELETE /api/admin/cuentas-bancarias/{id} */
    public function destroy(Request $request, int $id): Response
    {
        /** @var User $user */
        $user = $request->user();

        $cuenta = CuentaBancaria::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $cuenta->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, string>
     */
    private function validationMessages(): array
    {
        return [
            'banco.required' => 'El banco es obligatorio.',
            'banco.max' => 'El banco no puede superar 80 caracteres.',
            'tipo_cuenta.required' => 'El tipo de cuenta es obligatorio.',
            'tipo_cuenta.in' => 'El tipo de cuenta seleccionado no es válido.',
            'numero_cuenta.required' => 'El número de cuenta es obligatorio.',
            'numero_cuenta.max' => 'El número de cuenta no puede superar 30 caracteres.',
            'nombre_titular.required' => 'El nombre del titular es obligatorio.',
            'nombre_titular.max' => 'El nombre del titular no puede superar 100 caracteres.',
            'rut_titular.required' => 'El RUT del titular es obligatorio.',
            'rut_titular.max' => 'El RUT del titular no puede superar 12 caracteres.',
            'activa.boolean' => 'El estado activo debe ser verdadero o falso.',
        ];
    }
}
