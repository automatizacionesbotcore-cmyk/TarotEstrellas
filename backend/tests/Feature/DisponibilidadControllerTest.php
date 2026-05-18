<?php

namespace Tests\Feature;

use App\Models\PerfilEspecialista;
use App\Models\Role;
use App\Models\TipoConsulta;
use App\Models\User;
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
