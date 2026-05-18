<?php

namespace Tests\Feature;

use App\Jobs\ProcesarReembolsoJob;
use App\Jobs\ProcesarReembolsoPaypalJob;
use App\Jobs\ProcesarReembolsosPendientesJob;
use App\Models\Cita;
use App\Models\Pago;
use App\Models\Reembolso;
use App\Models\TipoConsulta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProcesarReembolsoJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        Cache::forget('paypal_access_token');
    }

    public function test_job_completa_reembolso_stripe_pendiente(): void
    {
        config(['services.stripe.secret' => 'sk_test_123']);

        Http::fake([
            'https://api.stripe.com/v1/refunds' => Http::response([
                'id' => 're_test_123',
                'status' => 'succeeded',
            ], 200),
        ]);

        $reembolso = $this->createReembolsoStripePendiente();

        (new ProcesarReembolsoJob($reembolso->id))->handle();

        $reembolso->refresh();

        $this->assertSame('completado', $reembolso->estado);
        $this->assertNotNull($reembolso->procesado_en);
        $this->assertSame('re_test_123', (string) data_get($reembolso->metadata, 'stripe_refund_id'));

        Http::assertSent(function ($request) use ($reembolso) {
            return $request->url() === 'https://api.stripe.com/v1/refunds'
                && $request['amount'] === 10000
                && $request['charge'] === $reembolso->pago->stripe_charge_id;
        });
    }

    public function test_job_marca_fallido_si_stripe_devuelve_error(): void
    {
        config(['services.stripe.secret' => 'sk_test_123']);

        Http::fake([
            'https://api.stripe.com/v1/refunds' => Http::response([
                'error' => ['message' => 'Charge already refunded'],
            ], 400),
        ]);

        $reembolso = $this->createReembolsoStripePendiente();

        (new ProcesarReembolsoJob($reembolso->id))->handle();

        $reembolso->refresh();

        $this->assertSame('fallido', $reembolso->estado);
        $this->assertNotNull($reembolso->procesado_en);
        $this->assertSame(400, (int) data_get($reembolso->metadata, 'stripe_status'));
    }

    public function test_job_completa_reembolso_paypal_pendiente(): void
    {
        config([
            'services.paypal.client_id' => 'paypal-client',
            'services.paypal.client_secret' => 'paypal-secret',
            'services.paypal.base_url' => 'https://api-m.sandbox.paypal.com',
        ]);

        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-token',
                'expires_in' => 3600,
            ], 200),
            'https://api-m.sandbox.paypal.com/v2/payments/captures/CAPTURE-123/refund' => Http::response([
                'id' => 'REFUND-123',
                'status' => 'COMPLETED',
            ], 201),
        ]);

        $reembolso = $this->createReembolsoPaypalPendiente();

        (new ProcesarReembolsoPaypalJob($reembolso->id))->handle(app(\App\Services\PaypalService::class));

        $reembolso->refresh();

        $this->assertSame('completado', $reembolso->estado);
        $this->assertNotNull($reembolso->procesado_en);
        $this->assertSame('REFUND-123', (string) data_get($reembolso->metadata, 'paypal_refund_id'));
        $this->assertSame('COMPLETED', (string) data_get($reembolso->metadata, 'paypal_status'));

        Http::assertSent(function ($request) use ($reembolso) {
            return $request->url() === 'https://api-m.sandbox.paypal.com/v2/payments/captures/CAPTURE-123/refund'
                && $request->hasHeader('PayPal-Request-Id', 'reembolso-'.$reembolso->uuid)
                && data_get($request->data(), 'amount.currency_code') === 'USD'
                && data_get($request->data(), 'amount.value') === '100.00';
        });
    }

    public function test_job_marca_fallido_si_paypal_devuelve_error(): void
    {
        config([
            'services.paypal.client_id' => 'paypal-client',
            'services.paypal.client_secret' => 'paypal-secret',
            'services.paypal.base_url' => 'https://api-m.sandbox.paypal.com',
        ]);

        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-token',
                'expires_in' => 3600,
            ], 200),
            'https://api-m.sandbox.paypal.com/v2/payments/captures/CAPTURE-123/refund' => Http::response([
                'name' => 'UNPROCESSABLE_ENTITY',
                'message' => 'Refund amount exceeds capture amount.',
            ], 422),
        ]);

        $reembolso = $this->createReembolsoPaypalPendiente();

        (new ProcesarReembolsoPaypalJob($reembolso->id))->handle(app(\App\Services\PaypalService::class));

        $reembolso->refresh();

        $this->assertSame('fallido', $reembolso->estado);
        $this->assertNotNull($reembolso->procesado_en);
        $this->assertStringContainsString('PayPal refund error', (string) data_get($reembolso->metadata, 'error'));
    }

    public function test_procesar_reembolsos_pendientes_dispatcha_jobs_por_cada_pendiente(): void
    {
        Queue::fake();

        $pendienteA = $this->createReembolsoStripePendiente();
        $pendienteB = $this->createReembolsoStripePendiente();
        $pendientePaypal = $this->createReembolsoPaypalPendiente();
        $completado = $this->createReembolsoStripePendiente();
        $completado->forceFill(['estado' => 'completado'])->save();

        (new ProcesarReembolsosPendientesJob())->handle();

        Queue::assertPushed(ProcesarReembolsoJob::class, function ($job) use ($pendienteA) {
            return $this->jobPropertyEquals($job, 'reembolsoId', $pendienteA->id);
        });

        Queue::assertPushed(ProcesarReembolsoJob::class, function ($job) use ($pendienteB) {
            return $this->jobPropertyEquals($job, 'reembolsoId', $pendienteB->id);
        });

        Queue::assertPushed(ProcesarReembolsoJob::class, 2);
        Queue::assertPushed(ProcesarReembolsoPaypalJob::class, function ($job) use ($pendientePaypal) {
            return $this->jobPropertyEquals($job, 'reembolsoId', $pendientePaypal->id);
        });
        Queue::assertPushed(ProcesarReembolsoPaypalJob::class, 1);
    }

    private function createReembolsoStripePendiente(): Reembolso
    {
        $cliente = User::factory()->create();
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        $cita = Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-REFUND-' . Str::random(10),
            'cliente_id' => $cliente->id,
            'especialista_id' => null,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => now()->addDay(),
            'fin_utc' => now()->addDay()->addMinutes(120),
            'duracion_minutos' => 120,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => 'cancelada_cliente',
            'canal_pago' => 'stripe',
            'precio_total_centavos' => 50000,
            'precio_final_centavos' => 50000,
            'moneda' => 'CLP',
            'es_primera_consulta' => false,
        ]);

        $pago = Pago::query()->create([
            'uuid' => (string) Str::uuid(),
            'cita_id' => $cita->id,
            'tipo' => 'abono_20',
            'canal' => 'stripe',
            'monto_centavos' => 10000,
            'moneda' => 'CLP',
            'estado' => 'completado',
            'stripe_payment_intent_id' => 'pi_test_' . Str::random(10),
            'stripe_charge_id' => 'ch_test_' . Str::random(10),
            'referencia_externa' => 'pi_test_' . Str::random(10),
            'pagado_en' => now(),
            'metadata' => ['source' => 'test'],
        ]);

        return Reembolso::query()->create([
            'uuid' => (string) Str::uuid(),
            'cita_id' => $cita->id,
            'cliente_id' => $cliente->id,
            'pago_id' => $pago->id,
            'monto_centavos' => 10000,
            'moneda' => 'CLP',
            'estado' => 'pendiente',
            'razon' => 'cancelacion_cliente_24h',
            'metodo' => 'mismo_medio_pago',
            'solicitado_en' => now(),
            'metadata' => ['source' => 'test'],
        ]);
    }

    private function createReembolsoPaypalPendiente(): Reembolso
    {
        $cliente = User::factory()->create();
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        $cita = Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-PAYPAL-' . Str::random(10),
            'cliente_id' => $cliente->id,
            'especialista_id' => null,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => now()->addDay(),
            'fin_utc' => now()->addDay()->addMinutes(120),
            'duracion_minutos' => 120,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => 'cancelada_cliente',
            'canal_pago' => 'paypal',
            'precio_total_centavos' => 50000,
            'precio_final_centavos' => 50000,
            'moneda' => 'USD',
            'es_primera_consulta' => false,
        ]);

        $pago = Pago::query()->create([
            'uuid' => (string) Str::uuid(),
            'cita_id' => $cita->id,
            'tipo' => 'abono_20',
            'canal' => 'paypal',
            'monto_centavos' => 10000,
            'moneda' => 'USD',
            'estado' => 'completado',
            'referencia_externa' => 'paypal:ORDER-123',
            'pagado_en' => now(),
            'metadata' => [
                'paypal_order_id' => 'ORDER-123',
                'paypal_capture_id' => 'CAPTURE-123',
            ],
        ]);

        return Reembolso::query()->create([
            'uuid' => (string) Str::uuid(),
            'cita_id' => $cita->id,
            'cliente_id' => $cliente->id,
            'pago_id' => $pago->id,
            'monto_centavos' => 10000,
            'moneda' => 'USD',
            'estado' => 'pendiente',
            'razon' => 'cancelacion_cliente_24h',
            'metodo' => 'mismo_medio_pago',
            'solicitado_en' => now(),
            'metadata' => ['source' => 'paypal-test'],
        ]);
    }

    private function jobPropertyEquals(object $job, string $property, mixed $expected): bool
    {
        $reflection = new \ReflectionObject($job);
        if (! $reflection->hasProperty($property)) {
            return false;
        }

        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);

        return $prop->getValue($job) === $expected;
    }
}






