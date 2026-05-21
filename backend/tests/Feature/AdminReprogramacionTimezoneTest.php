<?php

namespace Tests\Feature;

use App\Mail\CitaReprogramadaMail;
use App\Models\Cita;
use App\Models\Role;
use App\Models\TipoConsulta;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminReprogramacionTimezoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_reprogramacion_admin_guarda_utc_y_devuelve_hora_chile_correcta_aunque_app_timezone_no_sea_utc(): void
    {
        config(['app.timezone' => 'America/Santiago']);
        date_default_timezone_set('America/Santiago');
        Mail::fake();

        $especialista = $this->makeUserWithRole('admin_especialista', 'especialista-tz@example.com');
        $cliente = $this->makeUserWithRole('cliente', 'cliente-tz@example.com', 'America/Santiago');
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        $cita = Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-TZ-REPRO',
            'cliente_id' => $cliente->id,
            'especialista_id' => $especialista->id,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => CarbonImmutable::parse('2026-05-24 18:00:00', 'UTC'),
            'fin_utc' => CarbonImmutable::parse('2026-05-24 19:00:00', 'UTC'),
            'duracion_minutos' => 60,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => 'confirmada',
            'canal_pago' => 'stripe',
            'precio_total_centavos' => 45000,
            'precio_final_centavos' => 45000,
            'moneda' => 'CLP',
            'es_primera_consulta' => false,
        ]);

        $response = $this->actingAs($especialista, 'sanctum')
            ->postJson("/api/admin/citas/{$cita->uuid}/reprogramar", [
                'inicio_local' => '2026-05-25 14:00:00',
                'zona_horaria' => 'America/Santiago',
                'motivo' => 'Ajuste de agenda',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.inicio_utc', '2026-05-25T18:00:00+00:00');

        $rawInicio = DB::table('citas')->where('id', $cita->id)->value('inicio_utc');
        $this->assertSame('2026-05-25 18:00:00', $rawInicio);

        $fresh = $cita->fresh();
        $this->assertSame('2026-05-25 18:00:00', $fresh->inicio_utc->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-25 14:00:00', $fresh->inicio_utc->setTimezone('America/Santiago')->format('Y-m-d H:i:s'));

        $this->actingAs($cliente, 'sanctum')
            ->getJson("/api/citas/{$cita->uuid}")
            ->assertOk()
            ->assertJsonPath('data.inicio_utc', '2026-05-25T18:00:00.000000Z');

        Mail::assertSent(CitaReprogramadaMail::class);
    }

    private function makeUserWithRole(string $roleNombre, string $email, string $zonaHoraria = 'America/Santiago'): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'email_verified_at' => now(),
        ]);

        $user->profile()->create([
            'nombre' => 'Usuario TZ',
            'zona_horaria' => $zonaHoraria,
        ]);

        $user->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', $roleNombre)->value('id') => ['asignado_en' => now()],
        ]);

        return $user;
    }
}
