<?php

namespace Tests\Feature;

use App\Models\Consentimiento;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsentimientoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_index_devuelve_consentimientos_y_tipos(): void
    {
        $user = $this->makeUser();
        Consentimiento::create([
            'user_id' => $user->id, 'tipo' => 'terminos_uso',
            'version_documento' => '1.0', 'otorgado' => true, 'otorgado_en' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/me/consentimientos')->assertOk()
            ->assertJsonPath('data.0.tipo', 'terminos_uso')
            ->assertJsonPath('tipos.0', 'terminos_uso');
    }

    public function test_store_crea_consentimiento(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/me/consentimientos', [
            'tipo' => 'marketing_email',
            'otorgado' => true,
        ])->assertCreated()->assertJsonPath('data.tipo', 'marketing_email');

        $this->assertDatabaseHas('consentimientos', ['tipo' => 'marketing_email']);
    }

    public function test_store_rechaza_tipo_invalido(): void
    {
        Sanctum::actingAs($this->makeUser());
        $this->postJson('/api/me/consentimientos', [
            'tipo' => 'algo_inventado', 'otorgado' => true,
        ])->assertStatus(422);
    }

    private function makeUser(): User
    {
        $u = User::factory()->create(['email_verified_at' => now()]);
        $u->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'cliente')->value('id') => ['asignado_en' => now()],
        ]);
        return $u;
    }
}
