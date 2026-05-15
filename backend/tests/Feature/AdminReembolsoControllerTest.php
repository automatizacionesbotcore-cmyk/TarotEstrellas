<?php

namespace Tests\Feature;

use App\Jobs\ProcesarReembolsoJob;
use App\Jobs\ProcesarReembolsoPaypalJob;
use App\Models\Cita;
use App\Models\Pago;
use App\Models\Reembolso;
use App\Models\Role;
use App\Models\TipoConsulta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminReembolsoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_non_admin_cannot_access_admin_reembolsos_endpoints(): void
    {
        $cliente = $this->makeUserWithRole('cliente');
        Sanctum::actingAs($cliente);

        $this->getJson('/api/admin/reembolsos')->assertStatus(403);
        $this->postJson('/api/admin/reembolsos', [])->assertStatus(403);
        $this->postJson('/api/admin/reembolsos/uuid-fake/procesar', [])->assertStatus(403);
    }

    public function test_admin_can_list_reembolsos_with_filters(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $pendiente = $this->createReembolso('pendiente', 'cancelacion_24h', 'mismo_medio_pago');
        $this->createReembolso('completado', 'cancelacion_chachita', 'transferencia_manual');

        $this->getJson('/api/admin/reembolsos?estado=pendiente&razon=cancelacion_24h')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.uuid', $pendiente->uuid)
            ->assertJsonPath('data.0.estado', 'pendiente');
    }

    public function test_admin_can_create_manual_reembolso(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $reembolsoBase = $this->createReembolso('pendiente', 'cancelacion_24h', 'mismo_medio_pago');

        $this->postJson('/api/admin/reembolsos', [
            'cita_uuid' => $reembolsoBase->cita->uuid,
            'pago_uuid' => $reembolsoBase->pago->uuid,
            'monto_centavos' => 5000,
            'moneda' => 'CLP',
            'razon' => 'ajuste_manual',
            'metodo' => 'transferencia_manual',
            'nota_admin' => 'Compensacion por incidencia.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.razon', 'ajuste_manual')
            ->assertJsonPath('data.metodo', 'transferencia_manual');

        $this->assertDatabaseHas('reembolsos', [
            'cita_id' => $reembolsoBase->cita_id,
            'monto_centavos' => 5000,
            'razon' => 'ajuste_manual',
            'metodo' => 'transferencia_manual',
        ]);
    }

    public function test_admin_procesar_reembolso_stripe_enqueue_job(): void
    {
        Queue::fake();

        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $reembolso = $this->createReembolso('pendiente', 'cancelacion_24h', 'mismo_medio_pago');

        $this->postJson('/api/admin/reembolsos/'.$reembolso->uuid.'/procesar', [
            'accion' => 'procesar',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Reembolso encolado para procesamiento.');

        Queue::assertPushed(ProcesarReembolsoJob::class, function ($job) use ($reembolso) {
            $reflection = new \ReflectionObject($job);
            $prop = $reflection->getProperty('reembolsoId');
            $prop->setAccessible(true);

            return $prop->getValue($job) === $reembolso->id;
        });
    }

    public function test_admin_procesar_reembolso_paypal_enqueue_job(): void
    {
        Queue::fake();

        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $reembolso = $this->createReembolsoPaypal('pendiente', 'cancelacion_24h', 'mismo_medio_pago');

        $this->postJson('/api/admin/reembolsos/'.$reembolso->uuid.'/procesar', [
            'accion' => 'procesar',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Reembolso encolado para procesamiento.');

        Queue::assertPushed(ProcesarReembolsoPaypalJob::class, function ($job) use ($reembolso) {
            $reflection = new \ReflectionObject($job);
            $prop = $reflection->getProperty('reembolsoId');
            $prop->setAccessible(true);

            return $prop->getValue($job) === $reembolso->id;
        });
    }

    public function test_admin_can_mark_manual_transfer_reembolso_as_completed(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $reembolso = $this->createReembolso('pendiente', 'ajuste_manual', 'transferencia_manual');

        $this->postJson('/api/admin/reembolsos/'.$reembolso->uuid.'/procesar', [
            'accion' => 'marcar_completado',
            'referencia_manual' => 'TRF-9981',
            'nota_admin' => 'Transferencia realizada por banco.',
        ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'completado');

        $reembolso->refresh();
        $this->assertSame('completado', $reembolso->estado);
        $this->assertSame('TRF-9981', (string) data_get($reembolso->metadata, 'referencia_manual'));
    }

    public function test_admin_can_get_reembolsos_metrics(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $this->createReembolso('pendiente', 'cancelacion_24h', 'mismo_medio_pago');
        $this->createReembolso('completado', 'cancelacion_chachita', 'transferencia_manual');
        $this->createReembolso('fallido', 'ajuste_manual', 'mismo_medio_pago');

        $this->getJson('/api/admin/reembolsos/metricas')
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.by_status.pendiente.total', 1)
            ->assertJsonPath('data.by_status.completado.total', 1)
            ->assertJsonPath('data.by_status.fallido.total', 1)
            ->assertJsonPath('data.by_method.mismo_medio_pago.total', 2)
            ->assertJsonPath('data.by_method.transferencia_manual.total', 1);
    }

    public function test_reembolsos_metrics_rejects_invalid_date_range(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/reembolsos/metricas?from_date=2026-04-20&to_date=2026-04-10')
            ->assertStatus(422)
            ->assertJsonPath('errors.from_date.0', 'El rango de fechas es invalido.');
    }

    public function test_admin_can_export_reembolsos_csv_with_filters(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $pendiente = $this->createReembolso('pendiente', 'cancelacion_24h', 'mismo_medio_pago');
        $this->createReembolso('completado', 'cancelacion_chachita', 'transferencia_manual');

        $response = $this->get('/api/admin/reembolsos/export?estado=pendiente');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('uuid,cita_uuid,codigo_referencia', $csv);
        $this->assertStringContainsString((string) $pendiente->uuid, $csv);
        $this->assertStringNotContainsString('cancelacion_chachita', $csv);
    }

    public function test_non_admin_cannot_export_reembolsos_csv(): void
    {
        $cliente = $this->makeUserWithRole('cliente');
        Sanctum::actingAs($cliente);

        $this->getJson('/api/admin/reembolsos/export')->assertStatus(403);
    }

    public function test_reembolsos_export_rejects_invalid_date_range(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/reembolsos/export?from_date=2026-04-20&to_date=2026-04-10')
            ->assertStatus(422)
            ->assertJsonPath('errors.from_date.0', 'El rango de fechas es invalido.');
    }

    public function test_admin_can_get_reembolso_detail_with_timeline(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $reembolso = $this->createReembolso('completado', 'ajuste_manual', 'transferencia_manual');
        $reembolso->metadata = [
            'creado_por_admin_id' => $admin->id,
            'nota_admin' => 'Caso revisado y cerrado.',
            'procesado_manual_por_admin_id' => $admin->id,
            'referencia_manual' => 'TRF-TIMELINE-1',
        ];
        $reembolso->procesado_en = now();
        $reembolso->save();

        $this->getJson('/api/admin/reembolsos/'.$reembolso->uuid)
            ->assertOk()
            ->assertJsonPath('data.reembolso.uuid', $reembolso->uuid)
            ->assertJsonPath('data.reembolso.estado', 'completado')
            ->assertJsonCount(2, 'data.timeline')
            ->assertJsonPath('data.timeline.0.tipo', 'creado')
            ->assertJsonPath('data.timeline.1.tipo', 'procesado_manual')
            ->assertJsonPath('data.timeline.1.detalle.referencia_manual', 'TRF-TIMELINE-1');
    }

    public function test_non_admin_cannot_get_reembolso_detail(): void
    {
        $cliente = $this->makeUserWithRole('cliente');
        Sanctum::actingAs($cliente);

        $reembolso = $this->createReembolso('pendiente', 'cancelacion_24h', 'mismo_medio_pago');

        $this->getJson('/api/admin/reembolsos/'.$reembolso->uuid)->assertStatus(403);
    }

    public function test_reembolso_detail_timeline_includes_stripe_processing_event_from_legacy_metadata(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $reembolso = $this->createReembolso('completado', 'cancelacion_24h', 'mismo_medio_pago');
        $reembolso->metadata = [
            'stripe_refund_id' => 're_legacy_001',
            'stripe_status' => 'succeeded',
            'stripe_response' => [
                'id' => 're_legacy_001',
                'status' => 'succeeded',
                'balance_transaction' => 'txn_legacy_001',
            ],
        ];
        $reembolso->procesado_en = now();
        $reembolso->save();

        $response = $this->getJson('/api/admin/reembolsos/'.$reembolso->uuid)
            ->assertOk();

        $timeline = $response->json('data.timeline');
        $stripeEvent = collect($timeline)->firstWhere('tipo', 'procesado_stripe');

        $this->assertNotNull($stripeEvent);
        $this->assertSame('re_legacy_001', data_get($stripeEvent, 'detalle.refund_id'));
        $this->assertSame('succeeded', data_get($stripeEvent, 'detalle.status'));
    }

    public function test_reembolso_detail_timeline_includes_paypal_processing_event(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $reembolso = $this->createReembolsoPaypal('completado', 'cancelacion_24h', 'mismo_medio_pago');
        $reembolso->metadata = [
            'paypal_refund_id' => 'REFUND-ADM-001',
            'paypal_status' => 'COMPLETED',
            'paypal' => [
                'capture_id' => 'CAPTURE-ADM-001',
                'refund_id' => 'REFUND-ADM-001',
                'status' => 'COMPLETED',
                'processed_at' => now()->toIso8601String(),
            ],
        ];
        $reembolso->procesado_en = now();
        $reembolso->save();

        $response = $this->getJson('/api/admin/reembolsos/'.$reembolso->uuid)
            ->assertOk();

        $timeline = $response->json('data.timeline');
        $paypalEvent = collect($timeline)->firstWhere('tipo', 'procesado_paypal');

        $this->assertNotNull($paypalEvent);
        $this->assertSame('REFUND-ADM-001', data_get($paypalEvent, 'detalle.refund_id'));
        $this->assertSame('CAPTURE-ADM-001', data_get($paypalEvent, 'detalle.capture_id'));
        $this->assertSame('COMPLETED', data_get($paypalEvent, 'detalle.status'));
    }

    public function test_reembolso_detail_timeline_includes_failure_event_for_string_error_metadata(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $reembolso = $this->createReembolso('fallido', 'error_sistema', 'mismo_medio_pago');
        $reembolso->metadata = [
            'error' => 'Stripe devolvio error al crear reembolso.',
        ];
        $reembolso->save();

        $response = $this->getJson('/api/admin/reembolsos/'.$reembolso->uuid)
            ->assertOk();

        $timeline = $response->json('data.timeline');
        $failureEvent = collect($timeline)->firstWhere('tipo', 'fallo_procesamiento');

        $this->assertNotNull($failureEvent);
        $this->assertSame(
            'Stripe devolvio error al crear reembolso.',
            data_get($failureEvent, 'detalle.message')
        );
    }

    private function makeUserWithRole(string $roleName): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->profile()->create([
            'nombre' => ucfirst($roleName).' Test',
        ]);

        $roleId = Role::query()->where('nombre', $roleName)->value('id');
        $this->assertNotNull($roleId, 'Role '.$roleName.' should exist in seeders.');

        $user->roles()->syncWithoutDetaching([
            $roleId => ['asignado_en' => now()],
        ]);

        return $user;
    }

    private function createReembolso(string $estado, string $razon, string $metodo): Reembolso
    {
        $cliente = $this->makeUserWithRole('cliente');
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        $cita = Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-ADM-REF-'.Str::random(8),
            'cliente_id' => $cliente->id,
            'especialista_id' => null,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => now()->addDays(2),
            'fin_utc' => now()->addDays(2)->addMinutes(120),
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
            'stripe_payment_intent_id' => 'pi_admin_'.Str::random(8),
            'stripe_charge_id' => 'ch_admin_'.Str::random(8),
            'referencia_externa' => 'pi_admin_'.Str::random(8),
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
            'estado' => $estado,
            'razon' => $razon,
            'metodo' => $metodo,
            'solicitado_en' => now(),
            'procesado_en' => $estado === 'completado' ? now() : null,
            'metadata' => ['source' => 'test'],
        ]);
    }

    private function createReembolsoPaypal(string $estado, string $razon, string $metodo): Reembolso
    {
        $reembolso = $this->createReembolso($estado, $razon, $metodo);
        $reembolso->cita->forceFill([
            'canal_pago' => 'paypal',
            'moneda' => 'USD',
        ])->save();

        $reembolso->pago->forceFill([
            'canal' => 'paypal',
            'moneda' => 'USD',
            'stripe_payment_intent_id' => null,
            'stripe_charge_id' => null,
            'referencia_externa' => 'paypal:ORDER-ADM-001',
            'metadata' => [
                'paypal_order_id' => 'ORDER-ADM-001',
                'paypal_capture_id' => 'CAPTURE-ADM-001',
            ],
        ])->save();

        return $reembolso->fresh(['cita', 'pago']);
    }
}
