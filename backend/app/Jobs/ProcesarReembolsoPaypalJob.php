<?php

namespace App\Jobs;

use App\Models\Reembolso;
use App\Services\PaypalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcesarReembolsoPaypalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $reembolsoId)
    {
    }

    public function handle(PaypalService $paypal): void
    {
        $reembolso = Reembolso::query()->with('pago')->find($this->reembolsoId);
        if (! $reembolso || $reembolso->estado !== 'pendiente') {
            return;
        }

        if ($reembolso->monto_centavos <= 0) {
            $this->markFailed($reembolso, 'Monto de reembolso invalido.');
            return;
        }

        $pago = $reembolso->pago;
        if (! $pago || $pago->canal !== 'paypal') {
            $this->markFailed($reembolso, 'No existe pago PayPal asociado al reembolso.');
            return;
        }

        if (! $paypal->estaConfigurado()) {
            $this->markFailed($reembolso, 'PayPal no configurado para procesar reembolso.');
            return;
        }

        $captureId = (string) data_get($pago->metadata, 'paypal_capture_id', '');
        if ($captureId === '') {
            $this->markFailed($reembolso, 'No existe capture_id PayPal para procesar reembolso.');
            return;
        }

        try {
            $response = $paypal->reembolsarCaptura(
                captureId: $captureId,
                montoCentavos: (int) $reembolso->monto_centavos,
                moneda: (string) ($reembolso->moneda ?: $pago->moneda ?: 'USD'),
                requestId: 'reembolso-'.$reembolso->uuid,
                note: 'Reembolso TarotEstrellas '.$reembolso->uuid,
            );

            $paypalStatus = strtoupper((string) data_get($response, 'status', ''));
            if (! in_array($paypalStatus, ['COMPLETED', 'PENDING'], true)) {
                $this->markFailed($reembolso, 'PayPal no confirmo estado valido para reembolso.', [
                    'paypal_status' => $paypalStatus,
                    'paypal_response' => $response,
                ]);
                return;
            }

            $refundId = (string) data_get($response, 'id', '');

            $reembolso->forceFill([
                'estado' => 'completado',
                'procesado_en' => now(),
                'metadata' => array_merge((array) ($reembolso->metadata ?? []), [
                    'paypal_refund_id' => $refundId,
                    'paypal_status' => $paypalStatus,
                    'paypal' => [
                        'processed_at' => now()->toIso8601String(),
                        'capture_id' => $captureId,
                        'refund_id' => $refundId,
                        'status' => $paypalStatus,
                    ],
                    'paypal_response' => $response,
                ]),
            ])->save();
        } catch (Throwable $e) {
            $this->markFailed($reembolso, 'Error inesperado al procesar reembolso PayPal: '.$e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function markFailed(Reembolso $reembolso, string $message, array $extra = []): void
    {
        $reembolso->forceFill([
            'estado' => 'fallido',
            'procesado_en' => now(),
            'metadata' => array_merge((array) ($reembolso->metadata ?? []), [
                'error' => $message,
                'error_details' => [
                    'message' => $message,
                    'at' => now()->toIso8601String(),
                ],
            ], $extra),
        ])->save();
    }
}
