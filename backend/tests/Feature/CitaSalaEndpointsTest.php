<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Consentimiento;
use App\Models\TipoConsulta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CitaSalaEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_sala_endpoints_require_authentication(): void
    {
        $uuid = (string) Str::uuid();

        $this->getJson('/api/citas/'.$uuid.'/sala')->assertStatus(401);
        $this->postJson('/api/citas/'.$uuid.'/sala/consentimiento', [
            'acepta_grabacion' => true,
        ])->assertStatus(401);
        $this->postJson('/api/citas/'.$uuid.'/sala/entrada')->assertStatus(401);
    }

    public function test_cliente_can_get_sala_info_register_consent_and_mark_entry(): void
    {
        $cliente = User::factory()->create(['email_verified_at' => now()]);
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        $cita = Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-SALA-TEST',
            'cliente_id' => $cliente->id,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => now()->addDay(),
            'fin_utc' => now()->addDay()->addMinutes(120),
            'duracion_minutos' => 120,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => 'confirmada',
            'canal_pago' => 'stripe',
            'precio_total_centavos' => 50000,
            'precio_final_centavos' => 50000,
            'moneda' => 'CLP',
            'es_primera_consulta' => false,
        ]);

        Sanctum::actingAs($cliente);

        $this->getJson('/api/citas/'.$cita->uuid.'/sala')
            ->assertOk()
            ->assertJsonPath('data.cita.uuid', $cita->uuid)
            ->assertJsonStructure(['data' => ['url', 'token', 'sala_creada', 'pago_completado', 'en_horario', 'cita']]);

        $this->postJson('/api/citas/'.$cita->uuid.'/sala/consentimiento', [
            'acepta_grabacion' => true,
            'version_documento' => 'v1',
        ])
            ->assertCreated()
            ->assertJsonPath('data.cita_uuid', $cita->uuid);

        $this->assertDatabaseHas('consentimientos', [
            'user_id' => $cliente->id,
            'tipo' => 'grabacion_sala',
            'version_documento' => 'v1',
            'otorgado' => 1,
        ]);

        $this->postJson('/api/citas/'.$cita->uuid.'/sala/entrada')
            ->assertOk()
            ->assertJsonPath('data.estado', 'en_curso');

        $this->assertDatabaseHas('citas', [
            'id' => $cita->id,
            'estado' => 'en_curso',
        ]);
    }

    public function test_cannot_mark_entry_when_cita_state_is_invalid(): void
    {
        $cliente = User::factory()->create(['email_verified_at' => now()]);
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        $cita = Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-SALA-INVALID',
            'cliente_id' => $cliente->id,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => now()->addDay(),
            'fin_utc' => now()->addDay()->addMinutes(120),
            'duracion_minutos' => 120,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => 'expirada',
            'canal_pago' => 'stripe',
            'precio_total_centavos' => 50000,
            'precio_final_centavos' => 50000,
            'moneda' => 'CLP',
            'es_primera_consulta' => false,
        ]);

        Sanctum::actingAs($cliente);

        $this->postJson('/api/citas/'.$cita->uuid.'/sala/entrada')
            ->assertStatus(422);

        $this->assertDatabaseMissing('consentimientos', [
            'user_id' => $cliente->id,
            'tipo' => 'grabacion_sala',
        ]);

        $this->assertSame(0, Consentimiento::query()->where('user_id', $cliente->id)->count());
    }
}
