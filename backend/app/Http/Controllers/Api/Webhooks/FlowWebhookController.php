<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Mail\CitaConfirmadaMail;
use App\Mail\CitaReservadaMail;
use App\Models\Cita;
use App\Models\Pago;
use App\Services\FlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class FlowWebhookController extends Controller
{
    public function __invoke(Request $request, FlowService $flow): JsonResponse
    {
        $token = (string) $request->input('token', '');

        if ($token === '') {
            return response()->json(['message' => 'Token requerido.'], 400);
        }

        // Deduplication
        if (Pago::query()
            ->where('referencia_externa', 'flow:' . $token)
            ->where('estado', 'completado')
            ->exists()
        ) {
            return response()->json(['received' => true, 'duplicate' => true]);
        }

        try {
            $status = $flow->obtenerEstadoPorToken($token);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Error consultando Flow: ' . $e->getMessage()], 502);
        }

        $flowStatus    = (int) ($status['status'] ?? 0);
        $commerceOrder = (string) ($status['commerceOrder'] ?? '');
        $amount        = (int) ($status['amount'] ?? 0);

        // commerceOrder format: "cita:{uuid}:tipo:{tipo_pago}"
        $parts = explode(':', $commerceOrder);
        if (count($parts) < 4) {
            return response()->json(['message' => 'commerceOrder inválido.'], 400);
        }

        $citaUuid = $parts[1];
        $tipoPago = $parts[3];

        $cita = Cita::query()->where('uuid', $citaUuid)->first();
        if (! $cita) {
            return response()->json(['message' => 'Cita no encontrada.'], 404);
        }

        if ($flowStatus === 2) {
            $cita->pagos()->updateOrCreate(
                ['referencia_externa' => 'flow:' . $token],
                [
                    'uuid'               => (string) Str::uuid(),
                    'tipo'               => $tipoPago,
                    'canal'              => 'flow',
                    'monto_centavos'     => $amount,
                    'moneda'             => $cita->moneda,
                    'estado'             => 'completado',
                    'referencia_externa' => 'flow:' . $token,
                    'pagado_en'          => now(),
                    'metadata'           => [
                        'flow_token'          => $token,
                        'flow_order'          => $status['flowOrder'] ?? null,
                        'flow_commerce_order' => $commerceOrder,
                    ],
                ]
            );

            $this->avanzarEstadoCita($cita, $tipoPago);
        } elseif ($flowStatus === 3 || $flowStatus === 4) {
            $cita->pagos()->updateOrCreate(
                ['referencia_externa' => 'flow:' . $token],
                [
                    'uuid'               => (string) Str::uuid(),
                    'tipo'               => $tipoPago,
                    'canal'              => 'flow',
                    'monto_centavos'     => $amount,
                    'moneda'             => $cita->moneda,
                    'estado'             => 'fallido',
                    'referencia_externa' => 'flow:' . $token,
                    'fallo_razon'        => $flowStatus === 3
                        ? 'Pago rechazado por Flow.'
                        : 'Pago cancelado por el usuario.',
                    'metadata'           => ['flow_status' => $flowStatus, 'flow_token' => $token],
                ]
            );
        }

        return response()->json(['received' => true]);
    }

    private function avanzarEstadoCita(Cita $cita, string $tipoPago): void
    {
        if ($tipoPago === 'abono_20' && $cita->estado === 'pendiente_abono') {
            $cita->forceFill(['estado' => 'reservada'])->save();
            $cita->load(['cliente:id,email', 'cliente.profile:user_id,nombre', 'tipoConsulta:id,nombre,duracion_minutos']);
            if ($cita->cliente?->email) {
                Mail::to($cita->cliente->email)->send(new CitaReservadaMail($cita));
            }
            return;
        }

        if ($tipoPago === 'saldo_80' && $cita->estado === 'reservada') {
            $cita->forceFill(['estado' => 'confirmada', 'confirmada_en' => now()])->save();
            $cita->load(['cliente:id,email', 'cliente.profile:user_id,nombre', 'tipoConsulta:id,nombre,duracion_minutos']);
            if ($cita->cliente?->email) {
                Mail::to($cita->cliente->email)->send(new CitaConfirmadaMail($cita));
            }
        }
    }
}
