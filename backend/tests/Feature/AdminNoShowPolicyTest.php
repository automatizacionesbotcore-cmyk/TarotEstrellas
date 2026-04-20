<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Pago;
use App\Models\Role;
use App\Models\TipoConsulta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminNoShowPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_non_admin_cannot_mark_no_show(): void
    {
        $admin = $this->makeClient();
        $cliente = $this->makeClient();
        $cita = $this->createCitaForClient($cliente, now()->subMinutes(20), 'confirmada');

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/citas/'.$cita->uuid.'/no-show')
            ->assertStatus(403)
            ->assertJsonPath('message', 'No autorizado para marcar no-show.');
    }

    public function test_admin_marks_no_show_when_cliente_absent_and_especialista_present(): void
    {
        $admin = $this->makeAdmin();
        $cliente = $this->makeClient();
        $cita = $this->createCitaForClient($cliente, now()->subMinutes(20), 'confirmada');

        $this->createPagoCompletado($cita, 'abono_20', 10000);
        $this->createPagoCompletado($cita, 'saldo_80', 40000);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/citas/'.$cita->uuid.'/no-show', [
            'cliente_asistio' => false,
            'especialista_asistio' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'no_show')
            ->assertJsonPath('data.reembolsos_generados', 0)
            ->assertJsonPath('data.monto_reembolso_total_centavos', 0);

        $this->assertDatabaseMissing('reembolsos', [
            'cita_id' => $cita->id,
        ]);

        $this->assertDatabaseHas('citas_estados_historial', [
            'cita_id' => $cita->id,
            'estado_anterior' => 'confirmada',
            'estado_nuevo' => 'no_show',
        ]);
    }

    public function test_admin_marks_cancelada_chachita_and_generates_full_refunds(): void
    {
        $admin = $this->makeAdmin();
        $cliente = $this->makeClient();
        $cita = $this->createCitaForClient($cliente, now()->subMinutes(25), 'confirmada');

        $abono = $this->createPagoCompletado($cita, 'abono_20', 10000);
        $saldo = $this->createPagoCompletado($cita, 'saldo_80', 40000);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/citas/'.$cita->uuid.'/no-show', [
            'cliente_asistio' => false,
            'especialista_asistio' => false,
            'motivo' => 'Especialista no pudo conectarse',
        ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'cancelada_chachita')
            ->assertJsonPath('data.reembolsos_generados', 2)
            ->assertJsonPath('data.monto_reembolso_total_centavos', 50000);

        $this->assertDatabaseHas('reembolsos', [
            'cita_id' => $cita->id,
            'cliente_id' => $cliente->id,
            'pago_id' => $abono->id,
            'monto_centavos' => 10000,
            'estado' => 'pendiente',
            'razon' => 'cancelacion_chachita',
        ]);

        $this->assertDatabaseHas('reembolsos', [
            'cita_id' => $cita->id,
            'cliente_id' => $cliente->id,
            'pago_id' => $saldo->id,
            'monto_centavos' => 40000,
            'estado' => 'pendiente',
            'razon' => 'cancelacion_chachita',
        ]);

        $this->assertDatabaseHas('citas_estados_historial', [
            'cita_id' => $cita->id,
            'estado_anterior' => 'confirmada',
            'estado_nuevo' => 'cancelada_chachita',
            'motivo' => 'Especialista no pudo conectarse',
        ]);
    }

    public function test_admin_cannot_mark_no_show_if_cliente_attended(): void
    {
        $admin = $this->makeAdmin();
        $cliente = $this->makeClient();
        $cita = $this->createCitaForClient($cliente, now()->subMinutes(20), 'confirmada');

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/citas/'.$cita->uuid.'/no-show', [
            'cliente_asistio' => true,
            'especialista_asistio' => true,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'No corresponde marcar no-show si el cliente asistio.');
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

    private function makeAdmin(): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->profile()->create([
            'nombre' => 'Admin Test',
        ]);

        $user->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'admin_especialista')->value('id') => ['asignado_en' => now()],
        ]);

        return $user;
    }

    private function createCitaForClient(User $cliente, \DateTimeInterface $inicioUtc, string $estado): Cita
    {
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        return Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-NO-SHOW-'.Str::upper(Str::random(4)),
            'cliente_id' => $cliente->id,
            'especialista_id' => null,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => $inicioUtc,
            'fin_utc' => (clone $inicioUtc)->modify('+120 minutes'),
            'duracion_minutos' => 120,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => $estado,
            'canal_pago' => 'stripe',
            'precio_total_centavos' => 50000,
            'precio_final_centavos' => 50000,
            'moneda' => 'CLP',
            'es_primera_consulta' => false,
        ]);
    }

    private function createPagoCompletado(Cita $cita, string $tipo, int $montoCentavos): Pago
    {
        return Pago::query()->create([
            'uuid' => (string) Str::uuid(),
            'cita_id' => $cita->id,
            'tipo' => $tipo,
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
