<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Mail\CitaConfirmadaMail;
use App\Mail\CitaReservadaMail;
use App\Models\Cita;
use App\Models\Pago;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PaypalWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $event */
        $event     = $request->json()->all();
        $eventType = (string) Arr::get($event, 'event_type', '');
        $eventId   = (string) Arr::get($event, 'id', '');

        if ($eventId === '') {
            return response()->json(['message' => 'Invalid event id.'], 400);
        }

        // Deduplication via metadata search
        if (Pago::query()
            ->whereJsonContains('metadata->paypal_event_id', $eventId)
            ->exists()
        ) {
            return response()->json(['received' => true, 'duplicate' => true]);
        }

        if ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
            $this->handleCaptureCompleted($event);
        } elseif ($eventType === 'PAYMENT.CAPTURE.DENIED') {
            $this->handleCaptureDenied($event);
        }

        return response()->json(['received' => true]);
    }

    /** @param array<string, mixed> $event */
    private function handleCaptureCompleted(array $event): void
    {
        // reference_id was set when creating the order: "cita:{uuid}:tipo:{tipo_pago}"
        $referenceId = (string) Arr::get($event, 'resource.purchase_units.0.reference_id', '');
        $orderId     = (string) Arr::get($event, 'resource.supplementary_data.related_ids.order_id',
            Arr::get($event, 'resource.id', ''));

        $parts = explode(':', $referenceId);
        if (count($parts) < 4) {
            return;
        }

        $citaUuid = $parts[1];
        $tipoPago = $parts[3];

        $cita = Cita::query()->where('uuid', $citaUuid)->first();
        if (! $cita) {
            return;
        }

        $amount   = (float) Arr::get($event, 'resource.amount.value', 0);
        $currency = strtoupper((string) Arr::get($event, 'resource.amount.currency_code', $cita->moneda));

        // Convert to centavos (PayPal amounts are in major currency units)
        $amountCentavos = (int) round($amount * 100);

        $cita->pagos()->create([
            'uuid'               => (string) Str::uuid(),
            'tipo'               => $tipoPago,
            'canal'              => 'paypal',
            'monto_centavos'     => $amountCentavos,
            'moneda'             => $currency,
            'estado'             => 'completado',
            'referencia_externa' => 'paypal:' . $orderId,
            'pagado_en'          => now(),
            'metadata'           => [
                'paypal_event_id'   => Arr::get($event, 'id'),
                'paypal_order_id'   => $orderId,
                'paypal_capture_id' => Arr::get($event, 'resource.id'),
            ],
        ]);

        $this->avanzarEstadoCita($cita, $tipoPago);
    }

    /** @param array<string, mixed> $event */
    private function handleCaptureDenied(array $event): void
    {
        $orderId     = (string) Arr::get($event, 'resource.id', '');
        $referenceId = (string) Arr::get($event, 'resource.purchase_units.0.reference_id', '');

        $parts = explode(':', $referenceId);
        if (count($parts) < 4) {
            return;
        }

        $cita = Cita::query()->where('uuid', $parts[1])->first();
        if (! $cita) {
            return;
        }

        $cita->pagos()->create([
            'uuid'               => (string) Str::uuid(),
            'tipo'               => $parts[3],
            'canal'              => 'paypal',
            'monto_centavos'     => 0,
            'moneda'             => $cita->moneda,
            'estado'             => 'fallido',
            'referencia_externa' => 'paypal:' . $orderId,
            'fallo_razon'        => 'PayPal capture denied.',
            'metadata'           => ['paypal_event_id' => Arr::get($event, 'id')],
        ]);
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
