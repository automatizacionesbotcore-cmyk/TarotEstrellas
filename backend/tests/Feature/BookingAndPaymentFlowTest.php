<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Cita;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingAndPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_public_catalog_and_detail_endpoints_return_seeded_data(): void
    {
        $list = $this->getJson('/api/public/tipos-consulta');

        $list->assertOk();
        $list->assertJsonPath('data.0.slug', 'tarot');

        $detail = $this->getJson('/api/public/tipos-consulta/tarot');
        $detail
            ->assertOk()
            ->assertJsonPath('data.slug', 'tarot');
    }

    public function test_public_disponibilidad_excludes_slot_taken_by_existing_booking(): void
    {
        $user = $this->makeAuthenticatedClient();

        Sanctum::actingAs($user);
        $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'tarot',
            'inicio_local' => '2026-04-25 10:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
        ])->assertCreated();

        $response = $this->getJson('/api/public/disponibilidad?tipo_consulta_slug=tarot&date=2026-04-25&tz=America/Santiago');

        $response->assertOk();
        $response->assertJsonMissing([
            'inicio_local' => '2026-04-25 10:00:00',
        ]);
    }

    public function test_client_can_create_cita_and_list_own_citas(): void
    {
        $user = $this->makeAuthenticatedClient();

        Sanctum::actingAs($user);

        $create = $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'tarot',
            'inicio_local' => '2026-04-27 11:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
            'tema_principal' => 'amor',
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('data.estado', 'pendiente_abono');

        $list = $this->getJson('/api/citas');
        $list
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.tema_principal', 'amor');
    }

    public function test_booking_uses_utc_slot_as_source_of_truth_and_keeps_client_timezone(): void
    {
        $user = $this->makeAuthenticatedClient();

        Sanctum::actingAs($user);

        $create = $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'tarot',
            'inicio_utc' => '2030-05-30T20:00:00Z',
            'inicio_local' => '2030-05-30 16:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
        ]);

        $create->assertCreated();

        $cita = Cita::query()->where('uuid', $create->json('data.uuid'))->firstOrFail();

        $this->assertSame('2030-05-30 20:00:00', $cita->inicio_utc->copy()->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('America/Santiago', $cita->zona_horaria_cliente);
    }

    public function test_abono_then_saldo_transitions_cita_to_confirmada(): void
    {
        $user = $this->makeAuthenticatedClient();

        Sanctum::actingAs($user);

        $create = $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'tarot',
            'inicio_local' => '2026-04-29 12:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
        ])->assertCreated();

        $uuid = $create->json('data.uuid');

        $abono = $this->postJson('/api/pagos/abono', [
            'cita_uuid' => $uuid,
        ]);

        $abono
            ->assertOk()
            ->assertJsonPath('data.tipo', 'abono_20')
            ->assertJsonPath('cita.estado', 'reservada');

        $saldo = $this->postJson('/api/pagos/abono', [
            'cita_uuid' => $uuid,
        ]);

        $saldo
            ->assertOk()
            ->assertJsonPath('data.tipo', 'saldo_80')
            ->assertJsonPath('cita.estado', 'confirmada');

        $pagos = $this->getJson('/api/pagos');
        $pagos->assertOk()->assertJsonCount(2, 'data');

        $primerPagoUuid = (string) $abono->json('data.uuid');
        $this->getJson('/api/pagos/'.$primerPagoUuid)
            ->assertOk()
            ->assertJsonPath('data.uuid', $primerPagoUuid)
            ->assertJsonPath('data.tipo', 'abono_20');
    }

    public function test_transferencia_is_approved_without_code_when_only_one_pending_transfer_exists(): void
    {
        $user = $this->makeAuthenticatedClient();
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'tarot',
            'inicio_local' => '2026-05-03 10:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
        ])->assertCreated();

        $precioFinal = (int) $create->json('data.precio_final_centavos');
        $montoMinimo = (int) round($precioFinal * 0.20);

        $response = $this->postJson('/api/pagos/transferencia/validar', [
            'transaccion_id' => 'TX-CHILE-0001',
            'monto_centavos' => $montoMinimo,
            'banco_destino' => 'BancoEstado',
            'cuenta_destino' => '1234567890',
            'rut_destino' => '11111111-1',
            'transferido_en' => now('America/Santiago')->toIso8601String(),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Transferencia validada correctamente.')
            ->assertJsonPath('data.referencia_externa', 'TX-CHILE-0001')
            ->assertJsonPath('cita.estado', 'reservada')
            ->assertJsonPath('cita.uuid', $create->json('data.uuid'));

        $this->assertDatabaseHas('validaciones_agente', [
            'cliente_id' => $user->id,
            'decision' => 'aprobar',
            'regla_1_cuenta_ok' => 1,
            'regla_2_monto_ok' => 1,
            'regla_4_unicidad_ok' => 1,
            'regla_5_ventana_tiempo_ok' => 1,
        ]);

        $comprobanteId = DB::table('comprobantes_transferencia')
            ->where('id_transaccion_bancaria', 'TX-CHILE-0001')
            ->value('id');

        $this->assertNotNull($comprobanteId);
        $this->assertDatabaseHas('validaciones_agente', [
            'cliente_id' => $user->id,
            'decision' => 'aprobar',
            'comprobante_id' => $comprobanteId,
        ]);
        $this->assertDatabaseHas('comprobantes_transferencia', [
            'id' => $comprobanteId,
            'estado_validacion' => 'aprobado_automatico',
        ]);
    }

    public function test_transferencia_rejects_duplicate_transaction_id(): void
    {
        $user = $this->makeAuthenticatedClient();
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'tarot',
            'inicio_local' => '2026-05-04 10:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
        ])->assertCreated();

        $precioFinal = (int) $create->json('data.precio_final_centavos');
        $montoMinimo = (int) round($precioFinal * 0.20);

        $payload = [
            'transaccion_id' => 'TX-CHILE-REPEATED',
            'monto_centavos' => $montoMinimo,
            'banco_destino' => 'BancoEstado',
            'cuenta_destino' => '1234567890',
            'rut_destino' => '11111111-1',
            'transferido_en' => now('America/Santiago')->toIso8601String(),
        ];

        $this->postJson('/api/pagos/transferencia/validar', $payload)->assertOk();

        $this->postJson('/api/pagos/transferencia/validar', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['transaccion_id']);

        $this->assertDatabaseHas('validaciones_agente', [
            'cliente_id' => $user->id,
            'decision' => 'rechazar',
            'regla_4_unicidad_ok' => 0,
            'razon' => 'El ID de transaccion ya fue utilizado.',
        ]);

        $comprobanteId = DB::table('comprobantes_transferencia')
            ->where('id_transaccion_bancaria', 'TX-CHILE-REPEATED')
            ->orderByDesc('id')
            ->value('id');

        $this->assertNotNull($comprobanteId);
        $this->assertDatabaseHas('validaciones_agente', [
            'cliente_id' => $user->id,
            'decision' => 'rechazar',
            'comprobante_id' => $comprobanteId,
        ]);
        $this->assertDatabaseHas('comprobantes_transferencia', [
            'id' => $comprobanteId,
            'estado_validacion' => 'rechazado_automatico',
        ]);
    }

    public function test_transferencia_requires_reference_when_multiple_pending_citas_exist(): void
    {
        $user = $this->makeAuthenticatedClient();
        Sanctum::actingAs($user);

        $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'tarot',
            'inicio_local' => '2026-05-05 10:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
        ])->assertCreated();

        $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'carta-astral',
            'inicio_local' => '2026-05-06 10:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
        ])->assertCreated();

        $this->postJson('/api/pagos/transferencia/validar', [
            'transaccion_id' => 'TX-CHILE-MULTI',
            'monto_centavos' => 100000,
            'banco_destino' => 'BancoEstado',
            'cuenta_destino' => '1234567890',
            'rut_destino' => '11111111-1',
            'transferido_en' => now('America/Santiago')->toIso8601String(),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['codigo_referencia']);

        $comprobanteId = DB::table('comprobantes_transferencia')
            ->where('id_transaccion_bancaria', 'TX-CHILE-MULTI')
            ->value('id');

        $this->assertNotNull($comprobanteId);
        $this->assertDatabaseHas('validaciones_agente', [
            'cliente_id' => $user->id,
            'decision' => 'revision_manual',
            'comprobante_id' => $comprobanteId,
        ]);
        $this->assertDatabaseHas('comprobantes_transferencia', [
            'id' => $comprobanteId,
            'estado_validacion' => 'revision_requerida',
        ]);
    }

    public function test_transferencia_stale_window_message_uses_dynamic_setting_minutes(): void
    {
        $user = $this->makeAuthenticatedClient();
        Sanctum::actingAs($user);

        AppSetting::query()->updateOrCreate(
            ['key' => 'MINUTOS_ANTIGUEDAD_COMPROBANTE'],
            [
                'category' => 'pagos_transferencia',
                'value' => '45',
                'editable_admin' => true,
            ]
        );

        $create = $this->postJson('/api/citas', [
            'tipo_consulta_slug' => 'tarot',
            'inicio_local' => '2026-05-08 10:00:00',
            'zona_horaria_cliente' => 'America/Santiago',
            'canal_pago' => 'transferencia',
        ])->assertCreated();

        $precioFinal = (int) $create->json('data.precio_final_centavos');
        $montoMinimo = (int) round($precioFinal * 0.20);

        $response = $this->postJson('/api/pagos/transferencia/validar', [
            'transaccion_id' => 'TX-CHILE-STALE-45',
            'monto_centavos' => $montoMinimo,
            'banco_destino' => 'BancoEstado',
            'cuenta_destino' => '1234567890',
            'rut_destino' => '11111111-1',
            'transferido_en' => now('America/Santiago')->subMinutes(46)->toIso8601String(),
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['transferido_en'])
            ->assertJsonPath('errors.transferido_en.0', 'La transferencia excede la ventana maxima de 45 minutos.');
    }

    private function makeAuthenticatedClient(): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->profile()->create([
            'nombre' => 'Cliente',
        ]);

        $user->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'cliente')->value('id') => ['asignado_en' => now()],
        ]);

        return $user;
    }
}

