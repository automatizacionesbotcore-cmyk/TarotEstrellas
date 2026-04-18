<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\TipoConsulta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StripeWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_secret']);

        $payload = json_encode([
            'id' => 'evt_invalid_sig',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_invalid_sig',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->postJson('/api/webhooks/stripe', json_decode($payload, true), [
            'Stripe-Signature' => 't=1,v1=invalid',
        ])->assertStatus(400);
    }

    public function test_payment_intent_succeeded_creates_pago_and_reserves_cita(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_secret']);

        $cliente = User::factory()->create();
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        $cita = Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-ABCD-EFGH',
            'cliente_id' => $cliente->id,
            'especialista_id' => null,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => now()->addDay(),
            'fin_utc' => now()->addDay()->addMinutes(120),
            'duracion_minutos' => 120,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => 'pendiente_abono',
            'canal_pago' => 'stripe',
            'precio_total_centavos' => 50000,
            'precio_final_centavos' => 50000,
            'moneda' => 'CLP',
            'reservada_hasta' => now()->addMinutes(30),
            'es_primera_consulta' => true,
        ]);

        $event = [
            'id' => 'evt_123456',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_123456',
                    'amount_received' => 10000,
                    'currency' => 'clp',
                    'latest_charge' => 'ch_123456',
                    'metadata' => [
                        'cita_uuid' => $cita->uuid,
                    ],
                ],
            ],
        ];

        $payload = json_encode($event, JSON_THROW_ON_ERROR);
        $signature = $this->makeStripeSignature($payload, 'whsec_test_secret');

        $this->call('POST', '/api/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature,
        ], $payload)->assertOk()->assertJsonPath('received', true);

        $this->assertDatabaseHas('pagos', [
            'stripe_payment_intent_id' => 'pi_123456',
            'estado' => 'completado',
            'canal' => 'stripe',
            'cita_id' => $cita->id,
        ]);

        $this->assertDatabaseHas('citas', [
            'id' => $cita->id,
            'estado' => 'reservada',
        ]);

        $this->call('POST', '/api/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature,
        ], $payload)->assertOk()->assertJsonPath('duplicate', true);

        $this->assertDatabaseCount('pagos', 1);
        $this->assertDatabaseHas('stripe_webhook_events', [
            'event_id' => 'evt_123456',
            'event_type' => 'payment_intent.succeeded',
        ]);
    }

    private function makeStripeSignature(string $payload, string $secret): string
    {
        $timestamp = time();
        $signedPayload = $timestamp.'.'.$payload;
        $signature = hash_hmac('sha256', $signedPayload, $secret);

        return 't='.$timestamp.',v1='.$signature;
    }
}
