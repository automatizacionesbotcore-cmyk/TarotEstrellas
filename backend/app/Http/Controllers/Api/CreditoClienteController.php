<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreditoCliente;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CreditoClienteController extends Controller
{
    public function misCreditos(Request $request): JsonResponse
    {
        $user = $request->user();

        $creditos = CreditoCliente::query()
            ->where('cliente_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        $disponiblesPorMoneda = CreditoCliente::query()
            ->where('cliente_id', $user->id)
            ->disponibles()
            ->selectRaw('moneda, SUM(monto_centavos) as total')
            ->groupBy('moneda')
            ->pluck('total', 'moneda');

        return response()->json([
            'data' => $creditos,
            'disponibles_por_moneda' => $disponiblesPorMoneda,
        ]);
    }

    public function indexAdmin(Request $request, string $uuid): JsonResponse
    {
        $cliente = User::where('uuid', $uuid)->firstOrFail();

        $creditos = CreditoCliente::query()
            ->where('cliente_id', $cliente->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $creditos]);
    }

    public function storeAdmin(Request $request, string $uuid): JsonResponse
    {
        $cliente = User::where('uuid', $uuid)->firstOrFail();

        $data = $request->validate([
            'origen'         => ['required', Rule::in(['ajuste_admin', 'promocion', 'reembolso', 'no_show'])],
            'monto_centavos' => ['required', 'integer', 'min:1'],
            'moneda'         => ['required', 'string', 'size:3'],
            'vigente_hasta'  => ['nullable', 'date', 'after:now'],
            'descripcion'    => ['nullable', 'string', 'max:255'],
        ]);

        $credito = CreditoCliente::create([
            ...$data,
            'cliente_id' => $cliente->id,
            'estado'     => 'disponible',
            'creado_por' => $request->user()->id,
        ]);

        return response()->json(['data' => $credito], 201);
    }

    public function anularAdmin(Request $request, int $id): JsonResponse
    {
        $credito = CreditoCliente::findOrFail($id);

        if ($credito->estado !== 'disponible') {
            return response()->json(['message' => 'Solo se pueden anular creditos disponibles.'], 422);
        }

        $credito->update(['estado' => 'anulado']);

        return response()->json(['data' => $credito]);
    }
}
