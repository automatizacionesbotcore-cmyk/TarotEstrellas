<?php

namespace Tests\Feature;

use App\Models\PerfilEspecialista;
use App\Models\Role;
use App\Models\TipoConsulta;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DisponibilidadControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_disponibilidad_publica_usa_horario_default_si_especialista_no_tiene_configuracion(): void
    {
        $especialista = $this->makeEspecialista();
        $tipo = TipoConsulta::query()->where('activo', true)->firstOrFail();
        $date = now('America/Santiago')->addDays(4)->format('Y-m-d');

        $response = $this->getJson('/api/disponibilidad?'.http_build_query([
            'tipo_consulta_slug' => $tipo->slug,
            'date' => $date,
            'tz' => 'America/Santiago',
            'especialista_id' => $especialista->id,
        ]));

        $response->assertOk()
            ->assertJsonPath('meta.especialista_id', $especialista->id);

        $this->assertGreaterThan(0, $response->json('meta.count'));
    }

    public function test_disponibilidad_publica_permite_agendar_para_manana_aunque_falten_menos_de_24_horas(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-19 20:30:00', 'America/Santiago'));

        $especialista = $this->makeEspecialista();
        $tipo = TipoConsulta::query()->where('activo', true)->firstOrFail();

        $response = $this->getJson('/api/disponibilidad?'.http_build_query([
            'tipo_consulta_slug' => $tipo->slug,
            'date' => '2026-05-20',
            'tz' => 'America/Santiago',
            'especialista_id' => $especialista->id,
        ]));

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.count'));
    }

    public function test_disponibilidad_publica_oculta_slots_con_menos_de_una_hora_de_anticipacion(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-20 09:01:00', 'America/Santiago'));

        $especialista = $this->makeEspecialista();
        $tipo = TipoConsulta::query()->where('activo', true)->firstOrFail();

        $response = $this->getJson('/api/disponibilidad?'.http_build_query([
            'tipo_consulta_slug' => $tipo->slug,
            'date' => '2026-05-20',
            'tz' => 'America/Santiago',
            'especialista_id' => $especialista->id,
        ]));

        $response->assertOk();
        $this->assertNotContains('2026-05-20 10:00:00', collect($response->json('data'))->pluck('inicio_local')->all());
    }

    public function test_disponibilidad_publica_deja_holgura_entre_slots(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-20 08:55:00', 'America/Santiago'));

        $especialista = $this->makeEspecialista();
        $tipo = TipoConsulta::query()->where('activo', true)->firstOrFail();

        $response = $this->getJson('/api/disponibilidad?'.http_build_query([
            'tipo_consulta_slug' => $tipo->slug,
            'date' => '2026-05-20',
            'tz' => 'America/Santiago',
            'especialista_id' => $especialista->id,
        ]));

        $response->assertOk();

        $slots = collect($response->json('data'))->pluck('inicio_local')->values()->all();

        $this->assertSame('2026-05-20 10:00:00', $slots[0]);
        $this->assertSame('2026-05-20 11:15:00', $slots[1]);
    }

    private function makeEspecialista(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'admin_especialista')->value('id') => ['asignado_en' => now()],
        ]);

        PerfilEspecialista::query()->create([
            'user_id' => $user->id,
            'slug' => 'especialista-'.Str::lower(Str::random(6)),
            'especialidad' => 'Tarot',
            'activo' => true,
            'orden_display' => 0,
        ]);

        return $user;
    }
}
