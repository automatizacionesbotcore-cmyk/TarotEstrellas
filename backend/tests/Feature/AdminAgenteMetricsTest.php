<?php

namespace Tests\Feature;

use App\Models\AgenteConversacion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAgenteMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_metrics_devuelve_totales_y_costos(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'admin_especialista')->value('id') => ['asignado_en' => now()],
        ]);
        $cliente = User::factory()->create();

        AgenteConversacion::create([
            'uuid' => (string) Str::uuid(),
            'cliente_id' => $cliente->id,
            'autor_user_id' => $admin->id,
            'autor_rol' => 'admin',
            'pregunta' => 'foo',
            'respuesta' => 'bar',
            'proveedor' => 'anthropic',
            'modelo' => 'claude-3-5-sonnet-latest',
            'tokens_in' => 1_000_000,
            'tokens_out' => 500_000,
            'latencia_ms' => 1200,
        ]);
        AgenteConversacion::create([
            'uuid' => (string) Str::uuid(),
            'cliente_id' => $cliente->id,
            'autor_user_id' => $cliente->id,
            'autor_rol' => 'cliente',
            'pregunta' => 'baz',
            'respuesta' => null,
            'proveedor' => 'anthropic',
            'modelo' => 'claude-3-5-haiku-latest',
            'tokens_in' => 2_000_000,
            'tokens_out' => 100_000,
            'latencia_ms' => 800,
            'error' => 'timeout',
        ]);

        Sanctum::actingAs($admin);
        $resp = $this->getJson('/api/admin/agente/metrics')->assertOk();

        // 1M*3 + 0.5M*15 = 3 + 7.5 = 10.5
        // 2M*0.8 + 0.1M*4 = 1.6 + 0.4 = 2.0
        // total = 12.5
        $resp->assertJsonPath('totales.conversaciones', 2)
             ->assertJsonPath('totales.errores', 1);
        $this->assertEqualsWithDelta(12.5, $resp->json('totales.costo_usd'), 0.001);
    }

    public function test_metrics_requiere_admin(): void
    {
        $u = User::factory()->create();
        Sanctum::actingAs($u);
        $this->getJson('/api/admin/agente/metrics')->assertForbidden();
    }
}
