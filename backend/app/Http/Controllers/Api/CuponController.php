<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Cupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CuponController extends Controller
{
    /**
     * POST /cupones/validar
     * Body: { codigo, cita_uuid }
     */
    public function validar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'codigo'     => 'required|string|max:30',
            'cita_uuid'  => 'required|uuid',
        ]);

        $cita = Cita::where('uuid', $data['cita_uuid'])
            ->where('cliente_id', $request->user()->id)
            ->whereIn('estado', ['pendiente_abono', 'reservada'])
            ->firstOrFail();

        $cupon = Cupon::where('codigo', strtoupper($data['codigo']))->first();

        if (! $cupon || ! $cupon->estaVigente()) {
            return response()->json(['message' => 'Cupón inválido o expirado.'], 422);
        }

        if (! $cupon->usosDisponibles()) {
            return response()->json(['message' => 'Este cupón ya alcanzó su límite de usos.'], 422);
        }

        // Validar monto mínimo
        if ($cupon->monto_minimo_centavos && $cita->precio_total_centavos < $cupon->monto_minimo_centavos) {
            return response()->json([
                'message' => 'El monto de la cita no alcanza el mínimo requerido para este cupón.',
            ], 422);
        }

        // Validar solo primera consulta
        if ($cupon->solo_primera_consulta && ! $cita->es_primera_consulta) {
            return response()->json(['message' => 'Este cupón es exclusivo para la primera consulta.'], 422);
        }

        // Validar usos por cliente
        $usosCliente = Cita::where('cliente_id', $request->user()->id)
            ->where('cupon_id', $cupon->id)
            ->whereNotIn('estado', ['expirada', 'cancelada_cliente', 'cancelada_especialista', 'cancelada'])
            ->count();

        if ($usosCliente >= $cupon->uso_maximo_por_cliente) {
            return response()->json(['message' => 'Ya usaste este cupón el máximo de veces permitidas.'], 422);
        }

        $descuentoCentavos = $cupon->calcularDescuento($cita->precio_total_centavos);
        $precioFinalCentavos = max(0, $cita->precio_total_centavos - $descuentoCentavos);

        return response()->json([
            'cupon' => [
                'id'                  => $cupon->id,
                'codigo'              => $cupon->codigo,
                'descripcion'         => $cupon->descripcion,
                'tipo_descuento'      => $cupon->tipo_descuento,
                'valor_descuento'     => $cupon->valor_descuento,
            ],
            'descuento_centavos'      => $descuentoCentavos,
            'precio_final_centavos'   => $precioFinalCentavos,
        ]);
    }

    /**
     * POST /citas/{uuid}/aplicar-cupon
     * Body: { codigo }
     */
    public function aplicar(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'codigo' => 'required|string|max:30',
        ]);

        $cita = Cita::where('uuid', $uuid)
            ->where('cliente_id', $request->user()->id)
            ->whereIn('estado', ['pendiente_abono', 'reservada'])
            ->firstOrFail();

        $cupon = Cupon::where('codigo', strtoupper($data['codigo']))->first();

        if (! $cupon || ! $cupon->estaVigente()) {
            return response()->json(['message' => 'Cupón inválido o expirado.'], 422);
        }

        if (! $cupon->usosDisponibles()) {
            return response()->json(['message' => 'Este cupón ya alcanzó su límite de usos.'], 422);
        }

        if ($cupon->monto_minimo_centavos && $cita->precio_total_centavos < $cupon->monto_minimo_centavos) {
            return response()->json([
                'message' => 'El monto de la cita no alcanza el mínimo requerido para este cupón.',
            ], 422);
        }

        if ($cupon->solo_primera_consulta && ! $cita->es_primera_consulta) {
            return response()->json(['message' => 'Este cupón es exclusivo para la primera consulta.'], 422);
        }

        $usosCliente = Cita::where('cliente_id', $request->user()->id)
            ->where('cupon_id', $cupon->id)
            ->whereNotIn('estado', ['expirada', 'cancelada_cliente', 'cancelada_especialista', 'cancelada'])
            ->count();

        if ($usosCliente >= $cupon->uso_maximo_por_cliente) {
            return response()->json(['message' => 'Ya usaste este cupón el máximo de veces permitidas.'], 422);
        }

        DB::transaction(function () use ($cita, $cupon) {
            $descuento = $cupon->calcularDescuento($cita->precio_total_centavos);
            $cita->update([
                'cupon_id'               => $cupon->id,
                'precio_final_centavos'  => max(0, $cita->precio_total_centavos - $descuento),
            ]);
            $cupon->increment('usos_totales');
        });

        $cita->refresh();

        return response()->json([
            'message'                => 'Cupón aplicado.',
            'precio_final_centavos'  => $cita->precio_final_centavos,
            'descuento_centavos'     => $cita->precio_total_centavos - $cita->precio_final_centavos,
        ]);
    }
}
