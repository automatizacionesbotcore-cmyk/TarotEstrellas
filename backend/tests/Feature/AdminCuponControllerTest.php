<?php

namespace Tests\Feature;

use App\Models\Cupon;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCuponControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_unauthenticated_recibe_401(): void
    {
        $this->getJson('/api/admin/cupones')->assertStatus(401);
    }

    public function test_cliente_no_admin_recibe_403(): void
    {
        Sanctum::actingAs($this->makeClient());
        $this->getJson('/api/admin/cupones')->assertStatus(403);
    }

    public function test_admin_lista_cupones(): void
    {
        Cupon::create([
            'codigo' => 'TEST10', 'tipo_descuento' => 'porcentaje', 'valor_descuento' => 10,
            'vigente_desde' => now()->subDay(), 'activo' => true,
        ]);
        Sanctum::actingAs($this->makeAdmin());
        $this->getJson('/api/admin/cupones')
            ->assertOk()
            ->assertJsonFragment(['codigo' => 'TEST10']);
    }

    public function test_admin_crea_cupon(): void
    {
        Sanctum::actingAs($this->makeAdmin());
        $this->postJson('/api/admin/cupones', [
            'codigo' => 'newpromo',
            'tipo_descuento' => 'porcentaje',
            'valor_descuento' => 15,
            'vigente_desde' => now()->toDateTimeString(),
            'vigente_hasta' => now()->addMonth()->toDateTimeString(),
            'uso_maximo_por_cliente' => 1,
            'activo' => true,
        ])->assertCreated()
          ->assertJsonPath('data.codigo', 'NEWPROMO');

        $this->assertDatabaseHas('cupones', ['codigo' => 'NEWPROMO']);
    }

    public function test_admin_no_puede_duplicar_codigo(): void
    {
        Cupon::create([
            'codigo' => 'DUP', 'tipo_descuento' => 'porcentaje', 'valor_descuento' => 5,
            'vigente_desde' => now(), 'activo' => true,
        ]);
        Sanctum::actingAs($this->makeAdmin());
        $this->postJson('/api/admin/cupones', [
            'codigo' => 'dup',
            'tipo_descuento' => 'porcentaje',
            'valor_descuento' => 5,
            'vigente_desde' => now()->toDateTimeString(),
        ])->assertStatus(422);
    }

    public function test_admin_actualiza_cupon(): void
    {
        $c = Cupon::create([
            'codigo' => 'EDIT', 'tipo_descuento' => 'porcentaje', 'valor_descuento' => 10,
            'vigente_desde' => now(), 'activo' => true,
        ]);
        Sanctum::actingAs($this->makeAdmin());
        $this->patchJson('/api/admin/cupones/EDIT', [
            'codigo' => 'EDIT',
            'tipo_descuento' => 'porcentaje',
            'valor_descuento' => 25,
            'vigente_desde' => now()->toDateTimeString(),
        ])->assertOk()
          ->assertJsonPath('data.valor_descuento', 25);
    }

    public function test_admin_toggle_cupon(): void
    {
        Cupon::create([
            'codigo' => 'TOG', 'tipo_descuento' => 'porcentaje', 'valor_descuento' => 5,
            'vigente_desde' => now(), 'activo' => true,
        ]);
        Sanctum::actingAs($this->makeAdmin());
        $this->postJson('/api/admin/cupones/TOG/toggle')
            ->assertOk()
            ->assertJsonPath('data.activo', false);
    }

    public function test_admin_elimina_cupon(): void
    {
        Cupon::create([
            'codigo' => 'DEL', 'tipo_descuento' => 'porcentaje', 'valor_descuento' => 5,
            'vigente_desde' => now(), 'activo' => true,
        ]);
        Sanctum::actingAs($this->makeAdmin());
        $this->deleteJson('/api/admin/cupones/DEL')->assertOk();
        $this->assertSoftDeleted('cupones', ['codigo' => 'DEL']);
    }

    private function makeClient(): User
    {
        $u = User::factory()->create(['email_verified_at' => now()]);
        $u->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'cliente')->value('id') => ['asignado_en' => now()],
        ]);
        return $u;
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
