<?php

namespace App\Jobs;

use App\Models\Cita;
use App\Models\ComprobanteTransferencia;
use App\Models\ValidacionAgente;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use App\Support\PaymentValidationConfig;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ValidarComprobanteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $comprobanteId;


    public function __construct(int $comprobanteId)
    {
        $this->comprobanteId = $comprobanteId;
    }

    public function handle(): void
    {
        $comprobante = ComprobanteTransferencia::query()
            ->with(['cita:id,cliente_id,codigo_referencia', 'pago:id,monto_centavos'])
            ->find($this->comprobanteId);

        if (! $comprobante) {
            return;
        }

        if ($comprobante->estado_validacion !== 'pendiente') {
            return;
        }

        $extraidos = (array) ($comprobante->datos_extraidos ?? []);
        $montoComprobante = isset($extraidos['monto_clp']) ? (int) $extraidos['monto_clp'] : null;
        $tx = isset($extraidos['id_transaccion']) ? (string) $extraidos['id_transaccion'] : null;
        $bancoDestino = isset($extraidos['banco_destino']) ? trim((string) $extraidos['banco_destino']) : null;
        $cuentaDestino = isset($extraidos['cuenta_destino']) ? trim((string) $extraidos['cuenta_destino']) : null;
        $rutDestino = trim((string) ($extraidos['rut_titular_destino'] ?? $extraidos['rut_destino'] ?? ''));

        $regla1CuentaOk = $bancoDestino === PaymentValidationConfig::bancoDestino()
            && $cuentaDestino === PaymentValidationConfig::cuentaDestino()
            && $rutDestino === PaymentValidationConfig::rutDestino();

        $regla2MontoOk = $montoComprobante !== null
            && $comprobante->pago_id
            && $comprobante->pago
            && $montoComprobante >= (int) $comprobante->pago->monto_centavos;

        $codigoReferencia = '';
        if ($comprobante->cita) {
            $codigoReferencia = (string) $comprobante->cita->codigo_referencia;
        }

        $regla3ReferenciaOk = $this->containsReferenceCode(
            (string) ($extraidos['mensaje_glosa'] ?? ''),
            $codigoReferencia
        );

        $regla4UnicidadOk = ! empty($tx) && ! ComprobanteTransferencia::query()
            ->where('id', '!=', $comprobante->id)
            ->where('id_transaccion_bancaria', $tx)
            ->exists();

        $regla5VentanaTiempoOk = $this->isWithinAllowedWindow($extraidos);

        $pendingTransferCitas = null;
        if (! $regla3ReferenciaOk) {
            $pendingTransferCitas = $this->countPendingTransferCitas($comprobante);
        }

        $matchFlexibleOk = ! $regla3ReferenciaOk
            && $pendingTransferCitas === 1;

        $decision = 'revision_manual';
        $razon = 'Datos insuficientes para aprobación automática.';
        $estadoValidacion = 'revision_requerida';

        if (! $regla4UnicidadOk && ! empty($tx)) {
            $decision = 'rechazar';
            $razon = 'ID de transacción duplicado detectado en comprobantes previos.';
            $estadoValidacion = 'rechazado_automatico';
        } elseif ($regla1CuentaOk && $regla2MontoOk && $regla3ReferenciaOk && $regla4UnicidadOk && $regla5VentanaTiempoOk) {
            $decision = 'aprobar';
            $razon = 'Comprobante validado automáticamente por reglas completas.';
            $estadoValidacion = 'aprobado_automatico';
        } elseif (
            $regla1CuentaOk
            && $regla2MontoOk
            && $regla4UnicidadOk
            && $regla5VentanaTiempoOk
            && ! $regla3ReferenciaOk
            && $pendingTransferCitas === 0
        ) {
            $decision = 'rechazar';
            $razon = 'No existe una cita pendiente asociable para match flexible sin codigo de referencia.';
            $estadoValidacion = 'rechazado_automatico';
        } elseif ($regla1CuentaOk && $regla2MontoOk && $regla4UnicidadOk && $regla5VentanaTiempoOk && $matchFlexibleOk) {
            $decision = 'aprobar';
            $razon = 'Comprobante validado por match flexible con cita pendiente única.';
            $estadoValidacion = 'aprobado_automatico';
        } elseif (
            $regla1CuentaOk
            && $regla2MontoOk
            && $regla4UnicidadOk
            && $regla5VentanaTiempoOk
            && ! $regla3ReferenciaOk
            && is_int($pendingTransferCitas)
            && $pendingTransferCitas > 1
        ) {
            $decision = 'revision_manual';
            $razon = 'Existen múltiples citas pendientes para asociar transferencia sin codigo de referencia.';
            $estadoValidacion = 'revision_requerida';
        }

        $comprobante->forceFill([
            'estado_validacion' => $estadoValidacion,
            'id_transaccion_bancaria' => $tx ?: $comprobante->id_transaccion_bancaria,
            'razon_rechazo' => $decision === 'rechazar' ? $razon : null,
            'validado_en' => now(),
        ])->save();

        if (! $comprobante->cita || ! $comprobante->cita->cliente_id) {
            return;
        }

        ValidacionAgente::query()->create([
            'comprobante_id' => $comprobante->id,
            'cliente_id' => $comprobante->cita->cliente_id,
            'cita_id' => $comprobante->cita_id,
            'pago_id' => $comprobante->pago_id,
            'regla_1_cuenta_ok' => $regla1CuentaOk,
            'regla_2_monto_ok' => $regla2MontoOk,
            'regla_3_referencia_ok' => $regla3ReferenciaOk,
            'regla_4_unicidad_ok' => $regla4UnicidadOk,
            'regla_5_ventana_tiempo_ok' => $regla5VentanaTiempoOk,
            'decision' => $decision,
            'razon' => $razon,
            'modelo_ia' => 'validar-comprobante-job-v1',
            'duracion_ms' => 0,
            'payload' => [
                'tx' => $tx,
                'monto_extraido' => $montoComprobante,
                'monto_esperado' => $comprobante->pago ? $comprobante->pago->monto_centavos : null,
                'banco_destino' => $bancoDestino,
                'cuenta_destino' => $cuentaDestino,
                'rut_destino' => $rutDestino,
                'match_flexible' => $matchFlexibleOk,
                'pending_transfer_citas' => $pendingTransferCitas,
            ],
        ]);
    }

    private function containsReferenceCode(string $glosa, string $codigoReferencia): bool
    {
        if ($codigoReferencia === '') {
            return false;
        }

        return str_contains(mb_strtoupper($glosa), mb_strtoupper($codigoReferencia));
    }

    private function isWithinAllowedWindow(array $extraidos): bool
    {
        $fecha = isset($extraidos['fecha_transferencia']) ? trim((string) $extraidos['fecha_transferencia']) : '';
        $hora = isset($extraidos['hora_transferencia']) ? trim((string) $extraidos['hora_transferencia']) : '';

        if ($fecha === '' || $hora === '') {
            return false;
        }

        try {
            $transferidoEn = CarbonImmutable::parse($fecha.' '.$hora, 'America/Santiago');
        } catch (\Throwable $e) {
            return false;
        }

        return ! $transferidoEn->lt(
            now('America/Santiago')->subMinutes(PaymentValidationConfig::minutosAntiguedadComprobante())
        );
    }

    private function countPendingTransferCitas(ComprobanteTransferencia $comprobante): int
    {
        $clienteId = $comprobante->cita ? $comprobante->cita->cliente_id : null;
        if (! $clienteId) {
            return 0;
        }

        return (int) Cita::query()
            ->where('cliente_id', $clienteId)
            ->where('canal_pago', 'transferencia')
            ->whereIn('estado', ['pendiente_abono', 'reservada'])
            ->count();
    }
}
