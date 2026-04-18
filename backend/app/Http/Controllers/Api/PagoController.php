<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\ComprobanteTransferencia;
use App\Models\Pago;
use App\Models\User;
use App\Models\ValidacionAgente;
use App\Support\PaymentValidationConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PagoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $pagos = Pago::query()
            ->with('cita:id,uuid,cliente_id,estado')
            ->whereHas('cita', function ($query) use ($user) {
                $query->where('cliente_id', $user->id);
            })
            ->latest('created_at')
            ->get();

        return response()->json([
            'data' => $pagos,
        ]);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $pago = Pago::query()
            ->with('cita:id,uuid,cliente_id,estado,codigo_referencia')
            ->where('uuid', $uuid)
            ->firstOrFail();

        $clienteIdCita = $pago->cita ? (int) $pago->cita->cliente_id : null;
        if ($clienteIdCita !== (int) $user->id && ! $user->isAdmin()) {
            return response()->json([
                'message' => 'No autorizado para ver este pago.',
            ], 403);
        }

        return response()->json([
            'data' => $pago,
        ]);
    }

    public function pagarAbono(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cita_uuid' => ['required', 'string', 'size:36'],
            'monto_centavos' => ['nullable', 'integer', 'min:1'],
            'canal' => ['nullable', 'in:stripe,transferencia,credito_cliente,membresia'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $cita = Cita::query()
            ->where('uuid', $validated['cita_uuid'])
            ->where('cliente_id', $user->id)
            ->firstOrFail();

        $abonoObjetivo = (int) round($cita->precio_final_centavos * 0.20);
        $saldoObjetivo = max(0, (int) $cita->precio_final_centavos - $abonoObjetivo);

        $tipoPago = null;
        $montoMinimo = 0;

        if ($cita->estado === 'pendiente_abono') {
            $tipoPago = 'abono_20';
            $montoMinimo = $abonoObjetivo;
        } elseif ($cita->estado === 'reservada') {
            $tipoPago = 'saldo_80';
            $montoMinimo = $saldoObjetivo;
        }

        if (! $tipoPago) {
            throw ValidationException::withMessages([
                'cita_uuid' => 'La cita no esta en estado valido para registrar pago.',
            ]);
        }

        $monto = (int) ($validated['monto_centavos'] ?? $montoMinimo);
        if ($monto < $montoMinimo) {
            throw ValidationException::withMessages([
                'monto_centavos' => 'El monto es inferior al minimo requerido para este pago.',
            ]);
        }

        $pago = Pago::query()->create([
            'uuid' => (string) Str::uuid(),
            'cita_id' => $cita->id,
            'tipo' => $tipoPago,
            'canal' => $validated['canal'] ?? $cita->canal_pago,
            'monto_centavos' => $monto,
            'moneda' => $cita->moneda,
            'estado' => 'completado',
            'pagado_en' => now(),
            'metadata' => [
                'simulado' => true,
            ],
        ]);

        if ($tipoPago === 'abono_20') {
            $cita->forceFill([
                'estado' => 'reservada',
            ])->save();
        } else {
            $cita->forceFill([
                'estado' => 'confirmada',
                'confirmada_en' => now(),
            ])->save();
        }

        return response()->json([
            'message' => 'Pago registrado correctamente.',
            'data' => $pago,
            'cita' => [
                'uuid' => $cita->uuid,
                'estado' => $cita->estado,
            ],
        ]);
    }

    public function validarTransferencia(Request $request): JsonResponse
    {
        $start = microtime(true);

        $validated = $request->validate([
            'cita_uuid' => ['nullable', 'string', 'size:36'],
            'codigo_referencia' => ['nullable', 'string', 'max:20'],
            'transaccion_id' => ['required', 'string', 'max:120'],
            'monto_centavos' => ['required', 'integer', 'min:1'],
            'banco_destino' => ['required', 'string', 'max:100'],
            'cuenta_destino' => ['required', 'string', 'max:50'],
            'rut_destino' => ['required', 'string', 'max:20'],
            'transferido_en' => ['required', 'date'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $comprobante = ComprobanteTransferencia::query()->create([
            'uuid' => (string) Str::uuid(),
            'id_transaccion_bancaria' => $validated['transaccion_id'],
            'estado_validacion' => 'pendiente',
            'datos_extraidos' => [
                'monto_centavos' => $validated['monto_centavos'],
                'banco_destino' => $validated['banco_destino'],
                'cuenta_destino' => $validated['cuenta_destino'],
                'rut_destino' => $validated['rut_destino'],
                'codigo_referencia' => $validated['codigo_referencia'] ?? null,
                'cita_uuid' => $validated['cita_uuid'] ?? null,
                'transferido_en' => $validated['transferido_en'],
            ],
        ]);

        $regla3ReferenciaOk = ! empty($validated['cita_uuid']) || ! empty($validated['codigo_referencia']);

        $transferidoEn = now()->parse($validated['transferido_en'])->setTimezone('America/Santiago');
        $regla5VentanaTiempoOk = ! $transferidoEn->lt(
            now('America/Santiago')->subMinutes(PaymentValidationConfig::minutosAntiguedadComprobante())
        );
        $minutosVentana = PaymentValidationConfig::minutosAntiguedadComprobante();

        $logValidacion = function (
            string $decision,
            string $razon,
            array $rules,
            ?Cita $cita = null,
            ?Pago $pago = null
        ) use ($validated, $user, $start, $comprobante): void {
            $estadoValidacion = 'pendiente';
            if ($decision === 'aprobar') {
                $estadoValidacion = 'aprobado_automatico';
            } elseif ($decision === 'rechazar') {
                $estadoValidacion = 'rechazado_automatico';
            } elseif ($decision === 'revision_manual') {
                $estadoValidacion = 'revision_requerida';
            }

            $citaId = $cita ? $cita->id : null;
            $pagoId = $pago ? $pago->id : null;

            $comprobante->forceFill([
                'cita_id' => $citaId,
                'pago_id' => $pagoId,
                'estado_validacion' => $estadoValidacion,
                'validado_en' => now(),
                'razon_rechazo' => $decision === 'rechazar' ? $razon : null,
            ])->save();

            ValidacionAgente::query()->create([
                'comprobante_id' => $comprobante->id,
                'cliente_id' => $user->id,
                'cita_id' => $citaId,
                'pago_id' => $pagoId,
                'regla_1_cuenta_ok' => $rules['regla_1_cuenta_ok'] ?? null,
                'regla_2_monto_ok' => $rules['regla_2_monto_ok'] ?? null,
                'regla_3_referencia_ok' => $rules['regla_3_referencia_ok'] ?? null,
                'regla_4_unicidad_ok' => $rules['regla_4_unicidad_ok'] ?? null,
                'regla_5_ventana_tiempo_ok' => $rules['regla_5_ventana_tiempo_ok'] ?? null,
                'decision' => $decision,
                'razon' => $razon,
                'duracion_ms' => (int) round((microtime(true) - $start) * 1000),
                'payload' => [
                    'transaccion_id' => $validated['transaccion_id'],
                    'monto_centavos' => $validated['monto_centavos'],
                    'banco_destino' => $validated['banco_destino'],
                    'cuenta_destino' => $validated['cuenta_destino'],
                    'rut_destino' => $validated['rut_destino'],
                    'codigo_referencia' => $validated['codigo_referencia'] ?? null,
                    'cita_uuid' => $validated['cita_uuid'] ?? null,
                ],
            ]);
        };

        if (! $regla5VentanaTiempoOk) {
            $logValidacion('rechazar', "La transferencia excede la ventana maxima de {$minutosVentana} minutos.", [
                'regla_3_referencia_ok' => $regla3ReferenciaOk,
                'regla_5_ventana_tiempo_ok' => false,
            ]);

            throw ValidationException::withMessages([
                'transferido_en' => "La transferencia excede la ventana maxima de {$minutosVentana} minutos.",
            ]);
        }

        $regla1CuentaOk = (
            trim($validated['banco_destino']) !== PaymentValidationConfig::bancoDestino()
            || trim($validated['cuenta_destino']) !== PaymentValidationConfig::cuentaDestino()
            || trim($validated['rut_destino']) !== PaymentValidationConfig::rutDestino()
        ) === false;

        if (! $regla1CuentaOk) {
            $logValidacion('rechazar', 'La cuenta destino no coincide con los datos bancarios registrados.', [
                'regla_1_cuenta_ok' => false,
                'regla_3_referencia_ok' => $regla3ReferenciaOk,
                'regla_5_ventana_tiempo_ok' => $regla5VentanaTiempoOk,
            ]);

            throw ValidationException::withMessages([
                'cuenta_destino' => 'La cuenta destino no coincide con los datos bancarios registrados.',
            ]);
        }

        $regla4UnicidadOk = ! Pago::query()->where('referencia_externa', $validated['transaccion_id'])->exists();
        if (! $regla4UnicidadOk) {
            $logValidacion('rechazar', 'El ID de transaccion ya fue utilizado.', [
                'regla_1_cuenta_ok' => $regla1CuentaOk,
                'regla_3_referencia_ok' => $regla3ReferenciaOk,
                'regla_4_unicidad_ok' => false,
                'regla_5_ventana_tiempo_ok' => $regla5VentanaTiempoOk,
            ]);

            throw ValidationException::withMessages([
                'transaccion_id' => 'El ID de transaccion ya fue utilizado.',
            ]);
        }

        $cita = null;
        if (! empty($validated['cita_uuid'])) {
            $cita = Cita::query()
                ->where('uuid', $validated['cita_uuid'])
                ->where('cliente_id', $user->id)
                ->firstOrFail();
        } elseif (! empty($validated['codigo_referencia'])) {
            $cita = Cita::query()
                ->where('codigo_referencia', $validated['codigo_referencia'])
                ->where('cliente_id', $user->id)
                ->first();
        }

        if (! $cita) {
            $pendientes = Cita::query()
                ->where('cliente_id', $user->id)
                ->where('canal_pago', 'transferencia')
                ->whereIn('estado', ['pendiente_abono', 'reservada'])
                ->orderBy('inicio_utc')
                ->get();

            if ($pendientes->count() === 1) {
                $cita = $pendientes->first();
            } elseif ($pendientes->count() > 1) {
                $logValidacion('revision_manual', 'Existen multiples citas pendientes para asociar la transferencia.', [
                    'regla_1_cuenta_ok' => $regla1CuentaOk,
                    'regla_3_referencia_ok' => false,
                    'regla_4_unicidad_ok' => $regla4UnicidadOk,
                    'regla_5_ventana_tiempo_ok' => $regla5VentanaTiempoOk,
                ]);

                throw ValidationException::withMessages([
                    'codigo_referencia' => 'Existen multiples citas pendientes. Se requiere codigo de referencia para validacion manual.',
                ]);
            } else {
                $logValidacion('rechazar', 'No existe una cita pendiente asociable a la transferencia.', [
                    'regla_1_cuenta_ok' => $regla1CuentaOk,
                    'regla_3_referencia_ok' => false,
                    'regla_4_unicidad_ok' => $regla4UnicidadOk,
                    'regla_5_ventana_tiempo_ok' => $regla5VentanaTiempoOk,
                ]);

                throw ValidationException::withMessages([
                    'codigo_referencia' => 'No existe una cita pendiente asociable a la transferencia.',
                ]);
            }
        }

        $abonoObjetivo = (int) round($cita->precio_final_centavos * 0.20);
        $saldoObjetivo = max(0, (int) $cita->precio_final_centavos - $abonoObjetivo);

        $tipoPago = null;
        $montoMinimo = 0;

        if ($cita->estado === 'pendiente_abono') {
            $tipoPago = 'abono_20';
            $montoMinimo = $abonoObjetivo;
        } elseif ($cita->estado === 'reservada') {
            $tipoPago = 'saldo_80';
            $montoMinimo = $saldoObjetivo;
        }

        if (! $tipoPago) {
            $logValidacion('rechazar', 'La cita no esta en estado valido para recibir transferencia.', [
                'regla_1_cuenta_ok' => $regla1CuentaOk,
                'regla_3_referencia_ok' => $regla3ReferenciaOk,
                'regla_4_unicidad_ok' => $regla4UnicidadOk,
                'regla_5_ventana_tiempo_ok' => $regla5VentanaTiempoOk,
            ], $cita);

            throw ValidationException::withMessages([
                'cita_uuid' => 'La cita no esta en estado valido para recibir transferencia.',
            ]);
        }

        $monto = (int) $validated['monto_centavos'];
        $regla2MontoOk = $monto >= $montoMinimo;

        if (! $regla2MontoOk) {
            $logValidacion('rechazar', 'El monto transferido es inferior al minimo requerido.', [
                'regla_1_cuenta_ok' => $regla1CuentaOk,
                'regla_2_monto_ok' => false,
                'regla_3_referencia_ok' => $regla3ReferenciaOk,
                'regla_4_unicidad_ok' => $regla4UnicidadOk,
                'regla_5_ventana_tiempo_ok' => $regla5VentanaTiempoOk,
            ], $cita);

            throw ValidationException::withMessages([
                'monto_centavos' => 'El monto transferido es inferior al minimo requerido.',
            ]);
        }

        $pago = Pago::query()->create([
            'uuid' => (string) Str::uuid(),
            'cita_id' => $cita->id,
            'tipo' => $tipoPago,
            'canal' => 'transferencia',
            'monto_centavos' => $monto,
            'moneda' => $cita->moneda,
            'estado' => 'completado',
            'referencia_externa' => $validated['transaccion_id'],
            'pagado_en' => $transferidoEn->clone()->setTimezone('UTC'),
            'metadata' => [
                'reglas_validadas' => [
                    'cuenta_destino' => true,
                    'monto_minimo' => true,
                    'codigo_o_match' => true,
                    'transaccion_unica' => true,
                    'ventana_30_min' => true,
                ],
                'banco_destino' => $validated['banco_destino'],
                'cuenta_destino' => $validated['cuenta_destino'],
                'rut_destino' => $validated['rut_destino'],
                'codigo_referencia_enviado' => $validated['codigo_referencia'] ?? null,
            ],
        ]);

        if ($tipoPago === 'abono_20') {
            $cita->forceFill(['estado' => 'reservada'])->save();
        } else {
            $cita->forceFill([
                'estado' => 'confirmada',
                'confirmada_en' => now(),
            ])->save();
        }

        $decisionAprobacion = $regla3ReferenciaOk ? 'aprobar' : 'aprobar';
        $razonAprobacion = $regla3ReferenciaOk
            ? 'Transferencia validada por reglas completas.'
            : 'Transferencia validada por match flexible con unica cita pendiente.';

        $logValidacion($decisionAprobacion, $razonAprobacion, [
            'regla_1_cuenta_ok' => $regla1CuentaOk,
            'regla_2_monto_ok' => $regla2MontoOk,
            'regla_3_referencia_ok' => $regla3ReferenciaOk,
            'regla_4_unicidad_ok' => $regla4UnicidadOk,
            'regla_5_ventana_tiempo_ok' => $regla5VentanaTiempoOk,
        ], $cita, $pago);

        return response()->json([
            'message' => 'Transferencia validada correctamente.',
            'data' => $pago,
            'cita' => [
                'uuid' => $cita->uuid,
                'estado' => $cita->estado,
                'codigo_referencia' => $cita->codigo_referencia,
            ],
        ]);
    }
}
