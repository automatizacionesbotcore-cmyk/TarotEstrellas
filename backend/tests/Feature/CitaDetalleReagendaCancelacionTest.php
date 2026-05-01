<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Pago;
use App\Models\Reagendamiento;
use App\Models\Reembolso;
use App\Models\Role;
use App\Models\TipoConsulta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CitaDetalleReagendaCancelacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_cliente_can_view_own_cita_detail(): void
    {
        $cliente = $this->makeClient();
        $cita = $this->createCitaForClient($cliente, now()->addDays(3), 'confirmada');

        Sanctum::actingAs($cliente);

        $this->getJson('/api/citas/'.$cita->uuid)
            ->assertOk()
            ->assertJsonPath('data.uuid', $cita->uuid)
            ->assertJsonPath('data.estado', 'confirmada');
    }

    public function test_cliente_cannot_view_other_client_cita_detail(): void
    {
        $clienteA = $this->makeClient();
        $clienteB = $this->makeClient();

        $citaB = $this->createCitaForClient($clienteB, now()->addDays(3), 'confirmada');

        Sanctum::actingAs($clienteA);

        $this->getJson('/api/citas/'.$citaB->uuid)
            ->assertNotFound();
    }

    public function test_cliente_can_cancel_reagendable_cita(): void
    {
        $cliente = $this->makeClient();
        $cita = $this->createCitaForClient($cliente, now()->addDays(2), 'confirmada');

        Sanctum::actingAs($cliente);

        $this->postJson('/api/citas/'.$cita->uuid.'/cancelar', [
            'motivo' => 'Viaje inesperado',
        ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'cancelada_cliente')
            ->assertJsonPath('data.motivo_cancelacion', 'Viaje inesperado');

        $cita->refresh();

        $this->assertSame('cancelada_cliente', $cita->estado);
        $this->assertNotNull($cita->cancelada_en);
        $this->assertSame('Viaje inesperado', $cita->motivo_cancelacion);

        $this->assertDatabaseHas('citas_estados_historial', [
            'cita_id' => $cita->id,
            'estado_anterior' => 'confirmada',
            'estado_nuevo' => 'cancelada_cliente',
            'motivo' => 'Viaje inesperado',
        ]);
    }

    public function test_cancelacion_mas_24h_crea_reembolso_del_abono_si_hay_cupo(): void
    {
        $cliente = $this->makeClient();
        $cita = $this->createCitaForClient($cliente, now()->addDays(3), 'confirmada');
        $this->createAbonoCompletado($cita, 10000);

        Sanctum::actingAs($cliente);

        $this->postJson('/api/citas/'.$cita->uuid.'/cancelar', [
            'motivo' => 'Cambio de planes',
        ])
            ->assertOk()
            ->assertJsonPath('data.reembolso.aplica', true)
            ->assertJsonPath('data.reembolso.monto_centavos', 10000)
            ->assertJsonPath('data.reembolso.estado', 'pendiente')
            ->assertJsonPath('data.reembolso.motivo_politica', 'reembolso_24h_aplicado');

        $this->assertDatabaseHas('reembolsos', [
            'cita_id' => $cita->id,
            'cliente_id' => $cliente->id,
            'monto_centavos' => 10000,
            'moneda' => 'CLP',
            'estado' => 'pendiente',
            'razon' => 'cancelacion_24h',
            'metodo' => 'mismo_medio_pago',
        ]);
    }

    public function test_segunda_cancelacion_mas_24h_no_crea_reembolso_por_limite(): void
    {
        $cliente = $this->makeClient();

        $citaA = $this->createCitaForClient($cliente, now()->addDays(3), 'confirmada');
        $citaB = $this->createCitaForClient($cliente, now()->addDays(5), 'confirmada');

        $this->createAbonoCompletado($citaA, 10000);
        $this->createAbonoCompletado($citaB, 10000);

        Sanctum::actingAs($cliente);

        $this->postJson('/api/citas/'.$citaA->uuid.'/cancelar')
            ->assertOk()
            ->assertJsonPath('data.reembolso.aplica', true);

        $this->postJson('/api/citas/'.$citaB->uuid.'/cancelar')
            ->assertOk()
            ->assertJsonPath('data.reembolso.aplica', false)
            ->assertJsonPath('data.reembolso.motivo_politica', 'limite_reembolsos_alcanzado');

        $this->assertSame(1, Reembolso::query()->count());
    }

    public function test_cancelacion_menos_24h_no_crea_reembolso(): void
    {
        $cliente = $this->makeClient();
        $cita = $this->createCitaForClient($cliente, now()->addHours(10), 'confirmada');
        $this->createAbonoCompletado($cita, 10000);

        Sanctum::actingAs($cliente);

        $this->postJson('/api/citas/'.$cita->uuid.'/cancelar', [
            'motivo' => 'No llego a tiempo',
        ])
            ->assertOk()
            ->assertJsonPath('data.reembolso.aplica', false)
            ->assertJsonPath('data.reembolso.motivo_politica', 'menos_de_24h_sin_reembolso');

        $this->assertDatabaseMissing('reembolsos', [
            'cita_id' => $cita->id,
            'cliente_id' => $cliente->id,
        ]);
    }

    public function test_cliente_cannot_cancel_finalizada_cita(): void
    {
        $cliente = $this->makeClient();
        $cita = $this->createCitaForClient($cliente, now()->subDay(), 'finalizada');

        Sanctum::actingAs($cliente);

        $this->postJson('/api/citas/'.$cita->uuid.'/cancelar')
            ->assertStatus(422)
            ->assertJsonPath('message', 'La cita no se puede cancelar en su estado actual.');
    }

    public function test_cliente_can_reagendar_cita_with_more_than_24_hours(): void
    {
        $cliente = $this->makeClient();
        $cita = $this->createCitaForClient($cliente, now()->addDays(3), 'confirmada');

        Sanctum::actingAs($cliente);

        $response = $this->postJson('/api/citas/'.$cita->uuid.'/reagendar', [
            'inicio_local' => now()->addDays(5)->setTime(11, 0, 0)->format('Y-m-d H:i:s'),
            'zona_horaria_cliente' => 'America/Santiago',
            'motivo' => 'Cambio de disponibilidad',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Cita reagendada correctamente.')
            ->assertJsonPath('data.cita_original_uuid', $cita->uuid)
            ->assertJsonPath('data.gratuito', true);

        $nuevaUuid = (string) $response->json('data.cita_nueva.uuid');

        $nuevaCita = Cita::query()->where('uuid', $nuevaUuid)->firstOrFail();

        $cita->refresh();

        $this->assertSame('reagendada', $cita->estado);
        $this->assertNotNull($cita->cancelada_en);
        $this->assertSame('America/Santiago', $nuevaCita->zona_horaria_cliente);
        $this->assertTrue($nuevaCita->inicio_utc->greaterThan(now()->addDays(4)));

        $this->assertDatabaseHas('reagendamientos', [
            'cita_original_id' => $cita->id,
            'cita_nueva_id' => $nuevaCita->id,
            'reagendado_por' => $cliente->id,
            'motivo' => 'Cambio de disponibilidad',
            'gratuito' => true,
        ]);

        $this->assertDatabaseHas('citas_estados_historial', [
            'cita_id' => $cita->id,
            'estado_anterior' => 'confirmada',
            'estado_nuevo' => 'reagendada',
        ]);

        $this->assertDatabaseHas('citas_estados_historial', [
            'cita_id' => $nuevaCita->id,
            'estado_anterior' => null,
            'estado_nuevo' => 'confirmada',
        ]);
    }

    public function test_cliente_cannot_reagendar_with_less_than_24_hours(): void
    {
        $cliente = $this->makeClient();
        $cita = $this->createCitaForClient($cliente, now()->addHours(10), 'confirmada');

        Sanctum::actingAs($cliente);

        $this->postJson('/api/citas/'.$cita->uuid.'/reagendar', [
            'inicio_local' => now()->addDays(2)->setTime(10, 0, 0)->format('Y-m-d H:i:s'),
            'zona_horaria_cliente' => 'America/Santiago',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Faltan menos de 24 horas para la cita. No es posible reagendar.');
    }

    public function test_cliente_cannot_reagendar_when_new_slot_collides(): void
    {
        $cliente = $this->makeClient();
        $otroCliente = $this->makeClient();

        $cita = $this->createCitaForClient($cliente, now()->addDays(4), 'confirmada');
        $colision = $this->createCitaForClient($otroCliente, now()->addDays(6)->setTime(10, 0, 0), 'confirmada');

        Sanctum::actingAs($cliente);

        $inicioLocal = $colision->inicio_utc->clone()->setTimezone('America/Santiago')->format('Y-m-d H:i:s');

        $this->postJson('/api/citas/'.$cita->uuid.'/reagendar', [
            'inicio_local' => $inicioLocal,
            'zona_horaria_cliente' => 'America/Santiago',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'El nuevo horario seleccionado no se encuentra disponible.');
    }

    public function test_second_reagendar_marks_non_gratuito(): void
    {
        $cliente = $this->makeClient();
        $citaA = $this->createCitaForClient($cliente, now()->addDays(5), 'confirmada');
        $citaB = $this->createCitaForClient($cliente, now()->addDays(6), 'confirmada');

        Sanctum::actingAs($cliente);

        $this->postJson('/api/citas/'.$citaA->uuid.'/reagendar', [
            'inicio_local' => now()->addDays(8)->setTime(10, 0, 0)->format('Y-m-d H:i:s'),
            'zona_horaria_cliente' => 'America/Santiago',
        ])->assertOk();

        $response = $this->postJson('/api/citas/'.$citaB->uuid.'/reagendar', [
            'inicio_local' => now()->addDays(9)->setTime(10, 0, 0)->format('Y-m-d H:i:s'),
            'zona_horaria_cliente' => 'America/Santiago',
        ])->assertOk();

        $this->assertFalse((bool) $response->json('data.gratuito'));

        $nuevaUuid = (string) $response->json('data.cita_nueva.uuid');
        $nuevaCita = Cita::query()->where('uuid', $nuevaUuid)->firstOrFail();

        $this->assertDatabaseHas('reagendamientos', [
            'cita_original_id' => $citaB->id,
            'cita_nueva_id' => $nuevaCita->id,
            'reagendado_por' => $cliente->id,
            'gratuito' => false,
        ]);
    }

    public function test_cliente_can_get_transferencia_datos_for_transfer_cita(): void
    {
        $cliente = $this->makeClient();
        $cita = $this->createCitaForClient($cliente, now()->addDays(2), 'reservada', 'transferencia');

        Sanctum::actingAs($cliente);

        $response = $this->getJson('/api/citas/'.$cita->uuid.'/pagar/transferencia/datos')
            ->assertOk()
            ->assertJsonPath('data.cita_uuid', $cita->uuid)
            ->assertJsonPath('data.monto_minimo_abono_centavos', 10000);

        // Las cuentas bancarias vienen como array (puede ser desde cuentas_bancarias o fallback AppSettings)
        $cuentas = $response->json('data.cuentas_bancarias');
        $this->assertIsArray($cuentas);
        $this->assertNotEmpty($cuentas);
        $this->assertEquals('BancoEstado', $cuentas[0]['banco']);
        $this->assertEquals('1234567890', $cuentas[0]['numero_cuenta']);
        $this->assertEquals('11111111-1', $cuentas[0]['rut_titular']);
    }

    public function test_cliente_cannot_get_transferencia_datos_for_stripe_cita(): void
    {
        $cliente = $this->makeClient();
        $cita = $this->createCitaForClient($cliente, now()->addDays(2), 'confirmada', 'stripe');

        Sanctum::actingAs($cliente);

        $this->getJson('/api/citas/'.$cita->uuid.'/pagar/transferencia/datos')
            ->assertStatus(422)
            ->assertJsonPath('message', 'La cita no utiliza pago por transferencia.');
    }

    private function makeClient(): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->profile()->create([
            'nombre' => 'Cliente Test',
        ]);

        $user->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'cliente')->value('id') => ['asignado_en' => now()],
        ]);

        return $user;
    }

    private function createCitaForClient(User $cliente, \DateTimeInterface $inicioUtc, string $estado, string $canalPago = 'stripe'): Cita
    {
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        return Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-DET-'.Str::upper(Str::random(6)),
            'cliente_id' => $cliente->id,
            'especialista_id' => null,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => $inicioUtc,
            'fin_utc' => (clone $inicioUtc)->modify('+120 minutes'),
            'duracion_minutos' => 120,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => $estado,
            'canal_pago' => $canalPago,
            'precio_total_centavos' => 50000,
            'precio_final_centavos' => 50000,
            'moneda' => 'CLP',
            'es_primera_consulta' => false,
        ]);
    }

    private function createAbonoCompletado(Cita $cita, int $montoCentavos): Pago
    {
        return Pago::query()->create([
            'uuid' => (string) Str::uuid(),
            'cita_id' => $cita->id,
            'tipo' => 'abono_20',
            'canal' => 'stripe',
            'monto_centavos' => $montoCentavos,
            'moneda' => $cita->moneda,
            'estado' => 'completado',
            'stripe_payment_intent_id' => 'pi_'.Str::lower(Str::random(24)),
            'stripe_charge_id' => 'ch_'.Str::lower(Str::random(24)),
            'referencia_externa' => 'pi_ref_'.Str::lower(Str::random(12)),
            'pagado_en' => now(),
        ]);
    }
}
