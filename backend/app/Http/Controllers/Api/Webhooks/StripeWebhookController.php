<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Mail\CitaConfirmadaMail;
use App\Mail\CitaReservadaMail;
use App\Models\Cita;
use App\Models\Pago;
use App\Models\StripeWebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature', '');
        $secret = (string) config('services.stripe.webhook_secret', '');

        if ($secret === '' || ! $this->isValidStripeSignature($payload, $signature, $secret)) {
            return response()->json([
                'message' => 'Invalid webhook signature.',
            ], 400);
        }

        /** @var array<string, mixed> $event */
        $event = json_decode($payload, true) ?? [];
        $eventId = (string) Arr::get($event, 'id', '');
        $eventType = (string) Arr::get($event, 'type', '');

        if ($eventId === '') {
            return response()->json([
                'message' => 'Invalid Stripe event id.',
            ], 400);
        }

        $storedEvent = StripeWebhookEvent::query()->firstOrCreate(
            ['event_id' => $eventId],
            [
                'event_type' => $eventType,
                'payload' => $event,
                'processed_at' => now(),
            ]
        );

        if (! $storedEvent->wasRecentlyCreated) {
            return response()->json([
                'received' => true,
                'duplicate' => true,
            ]);
        }

        if ($eventType === 'payment_intent.succeeded') {
            $this->handlePaymentIntentSucceeded($event);
        }

        if ($eventType === 'payment_intent.payment_failed') {
            $this->handlePaymentIntentFailed($event);
        }

        return response()->json([
            'received' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $event
     */
    private function handlePaymentIntentSucceeded(array $event): void
    {
        $intentId = (string) Arr::get($event, 'data.object.id', '');
        if ($intentId === '') {
            return;
        }

        if (Pago::query()->where('stripe_payment_intent_id', $intentId)->exists()) {
            return;
        }

        $citaUuid = (string) Arr::get($event, 'data.object.metadata.cita_uuid', '');
        if ($citaUuid === '') {
            return;
        }

        $cita = Cita::query()->where('uuid', $citaUuid)->first();
        if (! $cita) {
            return;
        }

        $monto = (int) Arr::get($event, 'data.object.amount_received', Arr::get($event, 'data.object.amount', 0));
        $currency = strtoupper((string) Arr::get($event, 'data.object.currency', $cita->moneda));

        $tipoPago = null;
        if ($cita->estado === 'pendiente_abono') {
            $tipoPago = 'abono_20';
        } elseif ($cita->estado === 'reservada') {
            $tipoPago = 'saldo_80';
        }

        if (! $tipoPago) {
            return;
        }

        Pago::query()->create([
            'uuid' => (string) Str::uuid(),
            'cita_id' => $cita->id,
            'tipo' => $tipoPago,
            'canal' => 'stripe',
            'monto_centavos' => max(0, $monto),
            'moneda' => $currency,
            'estado' => 'completado',
            'stripe_payment_intent_id' => $intentId,
            'stripe_charge_id' => (string) Arr::get($event, 'data.object.latest_charge', ''),
            'referencia_externa' => $intentId,
            'pagado_en' => now(),
            'metadata' => [
                'stripe_event_id' => (string) Arr::get($event, 'id', ''),
            ],
        ]);

        if ($tipoPago === 'abono_20') {
            $cita->forceFill(['estado' => 'reservada'])->save();
            $cita->load(['cliente:id,email', 'cliente.profile:user_id,nombre', 'tipoConsulta:id,nombre,duracion_minutos']);
            if ($cita->cliente?->email) {
                Mail::to($cita->cliente->email)->send(new CitaReservadaMail($cita));
            }
            return;
        }

        $cita->forceFill([
            'estado' => 'confirmada',
            'confirmada_en' => now(),
        ])->save();
        $cita->load(['cliente:id,email', 'cliente.profile:user_id,nombre', 'tipoConsulta:id,nombre,duracion_minutos']);
        if ($cita->cliente?->email) {
            Mail::to($cita->cliente->email)->send(new CitaConfirmadaMail($cita));
        }
    }

    /**
     * @param array<string, mixed> $event
     */
    private function handlePaymentIntentFailed(array $event): void
    {
        $intentId = (string) Arr::get($event, 'data.object.id', '');
        if ($intentId === '' || Pago::query()->where('stripe_payment_intent_id', $intentId)->exists()) {
            return;
        }

        $citaUuid = (string) Arr::get($event, 'data.object.metadata.cita_uuid', '');
        if ($citaUuid === '') {
            return;
        }

        $cita = Cita::query()->where('uuid', $citaUuid)->first();
        if (! $cita) {
            return;
        }

        $monto = (int) Arr::get($event, 'data.object.amount', 0);
        $currency = strtoupper((string) Arr::get($event, 'data.object.currency', $cita->moneda));
        $message = (string) Arr::get($event, 'data.object.last_payment_error.message', 'Stripe payment failed.');

        Pago::query()->create([
            'uuid' => (string) Str::uuid(),
            'cita_id' => $cita->id,
            'tipo' => $cita->estado === 'reservada' ? 'saldo_80' : 'abono_20',
            'canal' => 'stripe',
            'monto_centavos' => max(0, $monto),
            'moneda' => $currency,
            'estado' => 'fallido',
            'stripe_payment_intent_id' => $intentId,
            'referencia_externa' => $intentId,
            'fallo_razon' => Str::limit($message, 255, ''),
            'metadata' => [
                'stripe_event_id' => (string) Arr::get($event, 'id', ''),
            ],
        ]);
    }

    private function isValidStripeSignature(string $payload, string $signatureHeader, string $secret): bool
    {
        if ($signatureHeader === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $item) {
            $item = trim($item);
            if ($item === '' || ! str_contains($item, '=')) {
                continue;
            }

            [$k, $v] = array_map('trim', explode('=', $item, 2));
            $parts[$k][] = $v;
        }

        $timestamp = isset($parts['t'][0]) ? (int) $parts['t'][0] : 0;
        if ($timestamp <= 0) {
            return false;
        }

        if (abs(time() - $timestamp) > 300) {
            return false;
        }

        $signedPayload = $timestamp.'.'.$payload;
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        foreach ($parts['v1'] ?? [] as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }
}
