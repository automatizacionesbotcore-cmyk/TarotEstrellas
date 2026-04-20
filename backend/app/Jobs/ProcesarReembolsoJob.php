<?php

namespace App\Jobs;

use App\Models\Reembolso;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

class ProcesarReembolsoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $reembolsoId)
    {
    }

    public function handle(): void
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
        if (! $pago || $pago->canal !== 'stripe') {
            $this->markFailed($reembolso, 'No existe pago Stripe asociado al reembolso.');
            return;
        }

        $stripeSecret = (string) config('services.stripe.secret', '');
        if ($stripeSecret === '') {
            $this->markFailed($reembolso, 'STRIPE_SECRET no configurada para procesar reembolso.');
            return;
        }

        $chargeId = (string) ($pago->stripe_charge_id ?? '');
        $paymentIntentId = (string) ($pago->stripe_payment_intent_id ?? '');
        if ($chargeId === '' && $paymentIntentId === '') {
            $this->markFailed($reembolso, 'No existe charge o payment intent Stripe para procesar reembolso.');
            return;
        }

        try {
            $payload = [
                'amount' => (int) $reembolso->monto_centavos,
                'metadata' => [
                    'reembolso_uuid' => $reembolso->uuid,
                    'cita_id' => (string) $reembolso->cita_id,
                ],
            ];

            if ($chargeId !== '') {
                $payload['charge'] = $chargeId;
            } else {
                $payload['payment_intent'] = $paymentIntentId;
            }

            $response = Http::asForm()
                ->withToken($stripeSecret)
                ->timeout(45)
                ->post('https://api.stripe.com/v1/refunds', $payload);

            if (! $response->successful()) {
                $this->markFailed($reembolso, 'Stripe devolvio error al crear reembolso.', [
                    'stripe_status' => $response->status(),
                    'stripe_body' => $response->json() ?: $response->body(),
                ]);
                return;
            }

            $stripeStatus = (string) $response->json('status', '');
            $isSuccessful = in_array($stripeStatus, ['succeeded', 'pending', 'requires_action'], true);

            if (! $isSuccessful) {
                $this->markFailed($reembolso, 'Stripe no confirmo estado valido para reembolso.', [
                    'stripe_status' => $stripeStatus,
                    'stripe_response' => $response->json(),
                ]);
                return;
            }

            $reembolso->forceFill([
                'estado' => 'completado',
                'procesado_en' => now(),
                'metadata' => array_merge((array) ($reembolso->metadata ?? []), [
                    'stripe_refund_id' => (string) $response->json('id', ''),
                    'stripe_status' => $stripeStatus,
                    'stripe' => [
                        'processed_at' => now()->toIso8601String(),
                        'refund_id' => (string) $response->json('id', ''),
                        'status' => $stripeStatus,
                        'balance_transaction' => (string) $response->json('balance_transaction', ''),
                    ],
                    'stripe_response' => $response->json(),
                ]),
            ])->save();
        } catch (Throwable $e) {
            $this->markFailed($reembolso, 'Error inesperado al procesar reembolso: '.$e->getMessage());
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
