<?php

namespace Tests\Feature;

use App\Jobs\ValidarComprobanteJob;
use App\Models\Cita;
use App\Models\ComprobanteTransferencia;
use App\Models\Pago;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ComprobanteTransferenciaFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_cliente_can_list_and_view_own_comprobantes(): void
    {
        $cliente = $this->makeUserWithRole('cliente');
        Sanctum::actingAs($cliente);

        $create = $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'tarot',
            'inicio_local' => '2026-06-02 10:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
        ])->assertCreated();

        $precioFinal = (int) $create->json('data.precio_final_centavos');
        $montoMinimo = (int) round($precioFinal * 0.20);

        $this->postJson('/api/pagos/transferencia/validar', [
            'transaccion_id' => 'TX-COMP-001',
            'monto_centavos' => $montoMinimo,
            'banco_destino' => 'BancoEstado',
            'cuenta_destino' => '1234567890',
            'rut_destino' => '11111111-1',
            'transferido_en' => now('America/Santiago')->toIso8601String(),
        ])->assertOk();

        $list = $this->getJson('/api/pagos/comprobantes');
        $list
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_transaccion_bancaria', 'TX-COMP-001');

        $uuid = (string) $list->json('data.0.uuid');
        $this->getJson('/api/pagos/comprobantes/'.$uuid)
            ->assertOk()
            ->assertJsonPath('data.uuid', $uuid);
    }

    public function test_cliente_can_upload_transfer_receipt_file_and_create_linked_validacion(): void
    {
        Storage::fake('local');

        $cliente = $this->makeUserWithRole('cliente');
        Sanctum::actingAs($cliente);

        $create = $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'tarot',
            'inicio_local' => '2026-06-10 10:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
        ])->assertCreated();

        $citaUuid = (string) $create->json('data.uuid');

        $file = UploadedFile::fake()->createWithContent(
            'comprobante_TX-REAL-1234_15-04-2026_10:30_12345678-5.txt',
            "Transferencia\nMonto: 25.000\nCuenta: 1234567890\nID: TX-REAL-1234\n",
            'text/plain'
        );

        $response = $this->withHeader('Accept', 'application/json')
            ->post('/api/citas/'.$citaUuid.'/pagar/transferencia/comprobante', [
                'comprobante' => $file,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Comprobante subido correctamente.')
            ->assertJsonPath('data.cita_uuid', $citaUuid)
            ->assertJsonPath('data.id_transaccion_bancaria', 'TX-REAL-1234')
            ->assertJsonPath('data.estado_validacion', 'pendiente');

        $archivoPath = (string) $response->json('data.archivo_url');
        $this->assertTrue(Storage::disk('local')->exists($archivoPath));

        $comprobanteId = (int) DB::table('comprobantes_transferencia')
            ->where('uuid', $response->json('data.uuid'))
            ->value('id');

        $this->assertDatabaseHas('validaciones_agente', [
            'comprobante_id' => $comprobanteId,
            'cliente_id' => $cliente->id,
            'decision' => 'revision_manual',
            'modelo_ia' => 'upload-parser-v1',
        ]);
    }

    public function test_admin_can_set_manual_validation_and_link_validacion_agente(): void
    {
        $cliente = $this->makeUserWithRole('cliente');
        Sanctum::actingAs($cliente);

        $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'tarot',
            'inicio_local' => '2026-06-03 10:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
        ])->assertCreated();

        $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'carta-astral',
            'inicio_local' => '2026-06-04 10:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
        ])->assertCreated();

        $this->postJson('/api/pagos/transferencia/validar', [
            'transaccion_id' => 'TX-COMP-REV-001',
            'monto_centavos' => 100000,
            'banco_destino' => 'BancoEstado',
            'cuenta_destino' => '1234567890',
            'rut_destino' => '11111111-1',
            'transferido_en' => now('America/Santiago')->toIso8601String(),
        ])->assertStatus(422);

        $uuid = (string) DB::table('comprobantes_transferencia')
            ->where('id_transaccion_bancaria', 'TX-COMP-REV-001')
            ->value('uuid');

        $this->assertNotSame('', $uuid);

        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $this->patchJson('/api/pagos/comprobantes/'.$uuid.'/validacion-manual', [
            'estado_validacion' => 'rechazado_manual',
            'razon_rechazo' => 'Comprobante ilegible.',
            'razon' => 'Revision manual: datos inconsistentes.',
        ])
            ->assertOk()
            ->assertJsonPath('data.estado_validacion', 'rechazado_manual');

        $comprobanteId = (int) DB::table('comprobantes_transferencia')
            ->where('uuid', $uuid)
            ->value('id');

        $this->assertDatabaseHas('validaciones_agente', [
            'comprobante_id' => $comprobanteId,
            'decision' => 'rechazar',
            'modelo_ia' => 'manual-admin-v1',
            'razon' => 'Revision manual: datos inconsistentes.',
        ]);
    }

    public function test_cliente_cannot_apply_manual_validation(): void
    {
        $cliente = $this->makeUserWithRole('cliente');
        Sanctum::actingAs($cliente);

        $create = $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'tarot',
            'inicio_local' => '2026-06-05 10:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
        ])->assertCreated();

        $precioFinal = (int) $create->json('data.precio_final_centavos');
        $montoMinimo = (int) round($precioFinal * 0.20);

        $this->postJson('/api/pagos/transferencia/validar', [
            'transaccion_id' => 'TX-COMP-SEC-001',
            'monto_centavos' => $montoMinimo,
            'banco_destino' => 'BancoEstado',
            'cuenta_destino' => '1234567890',
            'rut_destino' => '11111111-1',
            'transferido_en' => now('America/Santiago')->toIso8601String(),
        ])->assertOk();

        $uuid = (string) DB::table('comprobantes_transferencia')
            ->where('id_transaccion_bancaria', 'TX-COMP-SEC-001')
            ->value('uuid');

        $this->patchJson('/api/pagos/comprobantes/'.$uuid.'/validacion-manual', [
            'estado_validacion' => 'aprobado_manual',
        ])->assertStatus(403);
    }

    public function test_admin_can_approve_comprobante_by_admin_endpoint(): void
    {
        $cliente = $this->makeUserWithRole('cliente');
        Sanctum::actingAs($cliente);

        $create = $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'tarot',
            'inicio_local' => '2026-06-12 10:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
        ])->assertCreated();

        $citaUuid = (string) $create->json('data.uuid');

        $this->withHeader('Accept', 'application/json')
            ->post('/api/citas/'.$citaUuid.'/pagar/transferencia/comprobante', [
                'comprobante' => UploadedFile::fake()->createWithContent('comp_TX-ADMIN-APPROVE.txt', 'TX-ADMIN-APPROVE'),
            ])->assertCreated();

        $comprobanteId = (int) DB::table('comprobantes_transferencia')
            ->where('id_transaccion_bancaria', 'TX-ADMIN-APPROVE')
            ->value('id');

        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/comprobantes/'.$comprobanteId.'/aprobar')
            ->assertOk()
            ->assertJsonPath('data.estado_validacion', 'aprobado_manual');

        $this->assertDatabaseHas('validaciones_agente', [
            'comprobante_id' => $comprobanteId,
            'decision' => 'aprobar',
            'modelo_ia' => 'manual-admin-v1',
        ]);
    }

    public function test_admin_can_reject_comprobante_by_admin_endpoint(): void
    {
        $cliente = $this->makeUserWithRole('cliente');
        Sanctum::actingAs($cliente);

        $create = $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'tarot',
            'inicio_local' => '2026-06-13 10:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
        ])->assertCreated();

        $citaUuid = (string) $create->json('data.uuid');

        $this->withHeader('Accept', 'application/json')
            ->post('/api/citas/'.$citaUuid.'/pagar/transferencia/comprobante', [
                'comprobante' => UploadedFile::fake()->createWithContent('comp_TX-ADMIN-REJECT.txt', 'TX-ADMIN-REJECT'),
            ])->assertCreated();

        $comprobanteId = (int) DB::table('comprobantes_transferencia')
            ->where('id_transaccion_bancaria', 'TX-ADMIN-REJECT')
            ->value('id');

        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/comprobantes/'.$comprobanteId.'/rechazar', [
            'razon_rechazo' => 'No coincide la evidencia del comprobante.',
        ])
            ->assertOk()
            ->assertJsonPath('data.estado_validacion', 'rechazado_manual');

        $this->assertDatabaseHas('validaciones_agente', [
            'comprobante_id' => $comprobanteId,
            'decision' => 'rechazar',
            'modelo_ia' => 'manual-admin-v1',
            'razon' => 'No coincide la evidencia del comprobante.',
        ]);
    }

    public function test_admin_can_filter_and_paginate_comprobantes_by_estado_and_transaccion(): void
    {
        $cliente = $this->makeUserWithRole('cliente');
        Sanctum::actingAs($cliente);

        $createA = $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'tarot',
            'inicio_local' => '2026-06-14 10:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
        ])->assertCreated();

        $createB = $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'tarot',
            'inicio_local' => '2026-06-14 12:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
        ])->assertCreated();

        $montoMinimoA = (int) round(((int) $createA->json('data.precio_final_centavos')) * 0.20);
        $montoMinimoB = (int) round(((int) $createB->json('data.precio_final_centavos')) * 0.20);

        $this->postJson('/api/pagos/transferencia/validar', [
            'cita_uuid' => $createA->json('data.uuid'),
            'transaccion_id' => 'TX-ADMIN-FILTER-OK',
            'monto_centavos' => $montoMinimoA,
            'banco_destino' => 'BancoEstado',
            'cuenta_destino' => '1234567890',
            'rut_destino' => '11111111-1',
            'transferido_en' => now('America/Santiago')->toIso8601String(),
        ])->assertOk();

        $this->postJson('/api/pagos/transferencia/validar', [
            'cita_uuid' => $createB->json('data.uuid'),
            'transaccion_id' => 'TX-ADMIN-FILTER-DUP',
            'monto_centavos' => $montoMinimoB,
            'banco_destino' => 'BancoEstado',
            'cuenta_destino' => '1234567890',
            'rut_destino' => '11111111-1',
            'transferido_en' => now('America/Santiago')->toIso8601String(),
        ])->assertOk();

        $this->postJson('/api/pagos/transferencia/validar', [
            'cita_uuid' => $createB->json('data.uuid'),
            'transaccion_id' => 'TX-ADMIN-FILTER-DUP',
            'monto_centavos' => $montoMinimoB,
            'banco_destino' => 'BancoEstado',
            'cuenta_destino' => '1234567890',
            'rut_destino' => '11111111-1',
            'transferido_en' => now('America/Santiago')->toIso8601String(),
        ])->assertStatus(422);

        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/pagos/comprobantes?estado_validacion=rechazado_automatico&transaccion_id=TX-ADMIN-FILTER-DUP&per_page=1');

        $response
            ->assertOk()
            ->assertJsonPath('per_page', 1)
            ->assertJsonPath('total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.estado_validacion', 'rechazado_automatico')
            ->assertJsonPath('data.0.id_transaccion_bancaria', 'TX-ADMIN-FILTER-DUP');
    }

    public function test_admin_can_filter_comprobantes_by_date_range(): void
    {
        $cliente = $this->makeUserWithRole('cliente');
        Sanctum::actingAs($cliente);

        $createA = $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'tarot',
            'inicio_local' => '2026-06-15 10:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
        ])->assertCreated();

        $createB = $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'tarot',
            'inicio_local' => '2026-06-15 12:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
        ])->assertCreated();

        $montoMinimoA = (int) round(((int) $createA->json('data.precio_final_centavos')) * 0.20);
        $montoMinimoB = (int) round(((int) $createB->json('data.precio_final_centavos')) * 0.20);

        $this->postJson('/api/pagos/transferencia/validar', [
            'cita_uuid' => $createA->json('data.uuid'),
            'transaccion_id' => 'TX-ADMIN-DATE-OLD',
            'monto_centavos' => $montoMinimoA,
            'banco_destino' => 'BancoEstado',
            'cuenta_destino' => '1234567890',
            'rut_destino' => '11111111-1',
            'transferido_en' => now('America/Santiago')->toIso8601String(),
        ])->assertOk();

        DB::table('comprobantes_transferencia')
            ->where('id_transaccion_bancaria', 'TX-ADMIN-DATE-OLD')
            ->update(['created_at' => now()->subDays(3)]);

        $this->postJson('/api/pagos/transferencia/validar', [
            'cita_uuid' => $createB->json('data.uuid'),
            'transaccion_id' => 'TX-ADMIN-DATE-NEW',
            'monto_centavos' => $montoMinimoB,
            'banco_destino' => 'BancoEstado',
            'cuenta_destino' => '1234567890',
            'rut_destino' => '11111111-1',
            'transferido_en' => now('America/Santiago')->toIso8601String(),
        ])->assertOk();

        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $from = now()->subDay()->format('Y-m-d');
        $to = now()->format('Y-m-d');

        $response = $this->getJson('/api/pagos/comprobantes?from_date='.$from.'&to_date='.$to);

        $response
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id_transaccion_bancaria', 'TX-ADMIN-DATE-NEW');
    }

    public function test_comprobantes_list_rejects_invalid_date_range(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $this->getJson('/api/pagos/comprobantes?from_date=2026-06-20&to_date=2026-06-10')
            ->assertStatus(422)
            ->assertJsonPath('message', 'El rango de fechas es invalido: from_date no puede ser mayor que to_date.');
    }

    public function test_admin_can_get_comprobantes_metrics(): void
    {
        $cliente = $this->makeUserWithRole('cliente');
        Sanctum::actingAs($cliente);

        $createA = $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'tarot',
            'inicio_local' => '2026-06-16 10:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
        ])->assertCreated();

        $createB = $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'tarot',
            'inicio_local' => '2026-06-16 12:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
        ])->assertCreated();

        $montoA = (int) round(((int) $createA->json('data.precio_final_centavos')) * 0.20);
        $montoB = (int) round(((int) $createB->json('data.precio_final_centavos')) * 0.20);

        $this->postJson('/api/pagos/transferencia/validar', [
            'cita_uuid' => $createA->json('data.uuid'),
            'transaccion_id' => 'TX-METRICS-OK',
            'monto_centavos' => $montoA,
            'banco_destino' => 'BancoEstado',
            'cuenta_destino' => '1234567890',
            'rut_destino' => '11111111-1',
            'transferido_en' => now('America/Santiago')->toIso8601String(),
        ])->assertOk();

        $this->postJson('/api/pagos/transferencia/validar', [
            'cita_uuid' => $createB->json('data.uuid'),
            'transaccion_id' => 'TX-METRICS-DUP',
            'monto_centavos' => $montoB,
            'banco_destino' => 'BancoEstado',
            'cuenta_destino' => '1234567890',
            'rut_destino' => '11111111-1',
            'transferido_en' => now('America/Santiago')->toIso8601String(),
        ])->assertOk();

        $this->postJson('/api/pagos/transferencia/validar', [
            'cita_uuid' => $createB->json('data.uuid'),
            'transaccion_id' => 'TX-METRICS-DUP',
            'monto_centavos' => $montoB,
            'banco_destino' => 'BancoEstado',
            'cuenta_destino' => '1234567890',
            'rut_destino' => '11111111-1',
            'transferido_en' => now('America/Santiago')->toIso8601String(),
        ])->assertStatus(422);

        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $revisionUuid = (string) DB::table('comprobantes_transferencia')
            ->where('id_transaccion_bancaria', 'TX-METRICS-DUP')
            ->where('estado_validacion', 'rechazado_automatico')
            ->value('uuid');

        $this->patchJson('/api/pagos/comprobantes/'.$revisionUuid.'/validacion-manual', [
            'estado_validacion' => 'aprobado_manual',
            'razon' => 'Revision manual aprobada para cierre de caso.',
        ])->assertOk();

        $this->getJson('/api/admin/comprobantes/metricas')
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.by_status.aprobado_automatico', 2)
            ->assertJsonPath('data.by_status.aprobado_manual', 1)
            ->assertJsonPath('data.by_status.rechazado_automatico', 0)
            ->assertJsonPath('data.last_24h', 3)
            ->assertJsonPath('data.manual_queue', 0)
            ->assertJsonPath('data.manual_resolved_24h', 1);
    }

    public function test_admin_can_filter_comprobantes_by_manual_decision(): void
    {
        $cliente = $this->makeUserWithRole('cliente');
        Sanctum::actingAs($cliente);

        $createA = $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'tarot',
            'inicio_local' => '2026-06-17 10:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
        ])->assertCreated();

        $createB = $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'tarot',
            'inicio_local' => '2026-06-17 12:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
        ])->assertCreated();

        $montoA = (int) round(((int) $createA->json('data.precio_final_centavos')) * 0.20);
        $montoB = (int) round(((int) $createB->json('data.precio_final_centavos')) * 0.20);

        $this->postJson('/api/pagos/transferencia/validar', [
            'cita_uuid' => $createA->json('data.uuid'),
            'transaccion_id' => 'TX-MANUAL-APPROVE',
            'monto_centavos' => $montoA,
            'banco_destino' => 'BancoEstado',
            'cuenta_destino' => '1234567890',
            'rut_destino' => '11111111-1',
            'transferido_en' => now('America/Santiago')->toIso8601String(),
        ])->assertOk();

        $this->postJson('/api/pagos/transferencia/validar', [
            'cita_uuid' => $createB->json('data.uuid'),
            'transaccion_id' => 'TX-MANUAL-REJECT',
            'monto_centavos' => $montoB,
            'banco_destino' => 'BancoEstado',
            'cuenta_destino' => '1234567890',
            'rut_destino' => '11111111-1',
            'transferido_en' => now('America/Santiago')->toIso8601String(),
        ])->assertOk();

        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $uuidApprove = (string) DB::table('comprobantes_transferencia')
            ->where('id_transaccion_bancaria', 'TX-MANUAL-APPROVE')
            ->value('uuid');

        $uuidReject = (string) DB::table('comprobantes_transferencia')
            ->where('id_transaccion_bancaria', 'TX-MANUAL-REJECT')
            ->value('uuid');

        $this->patchJson('/api/pagos/comprobantes/'.$uuidApprove.'/validacion-manual', [
            'estado_validacion' => 'aprobado_manual',
            'razon' => 'Aprobacion manual para prueba de filtro.',
        ])->assertOk();

        $this->patchJson('/api/pagos/comprobantes/'.$uuidReject.'/validacion-manual', [
            'estado_validacion' => 'rechazado_manual',
            'razon_rechazo' => 'Rechazo manual para prueba de filtro.',
        ])->assertOk();

        $this->getJson('/api/pagos/comprobantes?decision_manual=aprobado')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id_transaccion_bancaria', 'TX-MANUAL-APPROVE')
            ->assertJsonPath('data.0.estado_validacion', 'aprobado_manual');

        $this->getJson('/api/pagos/comprobantes?decision_manual=rechazado')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id_transaccion_bancaria', 'TX-MANUAL-REJECT')
            ->assertJsonPath('data.0.estado_validacion', 'rechazado_manual');
    }

    public function test_non_admin_cannot_get_comprobantes_metrics(): void
    {
        $cliente = $this->makeUserWithRole('cliente');
        Sanctum::actingAs($cliente);

        $this->getJson('/api/admin/comprobantes/metricas')
            ->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_get_comprobantes_metrics(): void
    {
        $this->getJson('/api/admin/comprobantes/metricas')
            ->assertStatus(401);
    }

    public function test_job_rejects_flexible_match_when_client_has_zero_pending_transfer_citas(): void
    {
        $cliente = $this->makeUserWithRole('cliente');
        Sanctum::actingAs($cliente);

        $create = $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'tarot',
            'inicio_local' => '2026-06-18 10:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
        ])->assertCreated();

        $citaUuid = (string) $create->json('data.uuid');
        $citaId = (int) DB::table('citas')->where('uuid', $citaUuid)->value('id');

        $pago = Pago::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'cita_id' => $citaId,
            'tipo' => 'abono_20',
            'canal' => 'transferencia',
            'monto_centavos' => 25000,
            'moneda' => 'CLP',
            'estado' => 'completado',
            'pagado_en' => now(),
        ]);

        Cita::query()->where('id', $citaId)->update([
            'estado' => 'confirmada',
            'confirmada_en' => now(),
        ]);

        $comprobante = ComprobanteTransferencia::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'cita_id' => $citaId,
            'pago_id' => $pago->id,
            'estado_validacion' => 'pendiente',
            'id_transaccion_bancaria' => 'TX-JOB-ZERO-PENDING',
            'datos_extraidos' => [
                'monto_clp' => 25000,
                'id_transaccion' => 'TX-JOB-ZERO-PENDING',
                'banco_destino' => 'BancoEstado',
                'cuenta_destino' => '1234567890',
                'rut_titular_destino' => '11111111-1',
                'fecha_transferencia' => now('America/Santiago')->format('Y-m-d'),
                'hora_transferencia' => now('America/Santiago')->format('H:i:s'),
                'mensaje_glosa' => 'SIN CODIGO DE REFERENCIA',
            ],
        ]);

        (new ValidarComprobanteJob($comprobante->id))->handle();

        $this->assertDatabaseHas('comprobantes_transferencia', [
            'id' => $comprobante->id,
            'estado_validacion' => 'rechazado_automatico',
            'id_transaccion_bancaria' => 'TX-JOB-ZERO-PENDING',
        ]);

        $this->assertDatabaseHas('validaciones_agente', [
            'comprobante_id' => $comprobante->id,
            'decision' => 'rechazar',
            'regla_3_referencia_ok' => 0,
            'regla_4_unicidad_ok' => 1,
            'regla_5_ventana_tiempo_ok' => 1,
        ]);
    }

    private function makeUserWithRole(string $roleNombre): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->profile()->create([
            'nombre' => 'Usuario',
        ]);

        $user->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', $roleNombre)->value('id') => ['asignado_en' => now()],
        ]);

        return $user;
    }
}
