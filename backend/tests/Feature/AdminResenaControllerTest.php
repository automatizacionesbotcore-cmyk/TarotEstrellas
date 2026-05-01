<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Resena;
use App\Models\Role;
use App\Models\TipoConsulta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminResenaControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_unauthenticated_recibe_401(): void
    {
        $this->getJson('/api/admin/resenas')->assertStatus(401);
    }

    public function test_admin_lista_resenas(): void
    {
        $admin = $this->makeAdmin();
        $this->makeResena(3, 'Buena');
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/resenas')->assertOk()->assertJsonFragment(['comentario' => 'Buena']);
    }

    public function test_admin_filtra_resenas_bajas(): void
    {
        $admin = $this->makeAdmin();
        $this->makeResena(5, 'Excelente');
        $this->makeResena(2, 'Mala');
        Sanctum::actingAs($admin);

        $resp = $this->getJson('/api/admin/resenas?puntuacion_max=2')->assertOk();
        $this->assertCount(1, $resp->json('data'));
        $this->assertSame('Mala', $resp->json('data.0.comentario'));
    }

    public function test_admin_responde_resena(): void
    {
        $admin = $this->makeAdmin();
        $resena = $this->makeResena(2, 'Mala');
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/resenas/'.$resena->uuid.'/responder', [
            'respuesta' => 'Lamentamos tu experiencia.',
        ])->assertOk()
          ->assertJsonPath('data.respuesta_admin', 'Lamentamos tu experiencia.');

        $this->assertDatabaseHas('resenas', [
            'id' => $resena->id,
            'respondida_por' => $admin->id,
        ]);
    }

    public function test_admin_oculta_resena(): void
    {
        $admin = $this->makeAdmin();
        $resena = $this->makeResena(1, 'Spam');
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/resenas/'.$resena->uuid.'/visibilidad')
            ->assertOk()
            ->assertJsonPath('data.visible', false);
    }

    public function test_filtro_sin_responder(): void
    {
        $admin = $this->makeAdmin();
        $r1 = $this->makeResena(2, 'A');
        $r2 = $this->makeResena(2, 'B');
        $r2->update(['respuesta_admin' => 'Gracias', 'respondida_en' => now(), 'respondida_por' => $admin->id]);
        Sanctum::actingAs($admin);

        $resp = $this->getJson('/api/admin/resenas?sin_responder=1')->assertOk();
        $this->assertCount(1, $resp->json('data'));
    }

    private function makeResena(int $puntuacion, string $comentario): Resena
    {
        $cliente = User::factory()->create();
        $especialista = User::factory()->create();
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();
        $cita = Cita::create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-R-'.Str::upper(Str::random(4)),
            'cliente_id' => $cliente->id,
            'especialista_id' => $especialista->id,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => now()->subDays(3),
            'fin_utc' => now()->subDays(3)->addMinutes(30),
            'duracion_minutos' => 30,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => 'finalizada',
            'canal_pago' => 'stripe',
            'precio_total_centavos' => 250000,
            'precio_final_centavos' => 250000,
            'moneda' => 'CLP',
            'es_primera_consulta' => false,
        ]);

        return Resena::create([
            'uuid' => (string) Str::uuid(),
            'cita_id' => $cita->id,
            'cliente_id' => $cliente->id,
            'especialista_id' => $especialista->id,
            'puntuacion' => $puntuacion,
            'comentario' => $comentario,
            'visible' => true,
        ]);
    }

    private function makeAdmin(): User
    {
        $u = User::factory()->create(['email_verified_at' => now()]);
        $u->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'admin_especialista')->value('id') => ['asignado_en' => now()],
        ]);
        return $u;
    }
}
