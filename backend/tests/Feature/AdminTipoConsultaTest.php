<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\TipoConsulta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTipoConsultaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $this->getJson('/api/admin/tipos-consulta')->assertStatus(401);
    }

    public function test_non_admin_user_gets_403(): void
    {
        $cliente = $this->makeUserWithRole('cliente');
        Sanctum::actingAs($cliente);

        $this->getJson('/api/admin/tipos-consulta')->assertStatus(403);
        $this->postJson('/api/admin/tipos-consulta', [])->assertStatus(403);
    }

    public function test_admin_can_list_all_tipos(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        TipoConsulta::factory()->create(['activo' => false, 'nombre' => 'Inactivo']);

        $response = $this->getJson('/api/admin/tipos-consulta');
        $response->assertOk();

        $data = $response->json('data');
        $nombres = collect($data)->pluck('nombre')->toArray();
        $this->assertContains('Inactivo', $nombres);
    }

    public function test_admin_can_create_tipo(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/tipos-consulta', [
            'nombre'                      => 'Lectura de Runas',
            'descripcion'                 => 'Interpretación de runas nórdicas.',
            'duracion_minutos'            => 45,
            'precio_referencial_centavos' => 3500000,
            'moneda'                      => 'CLP',
            'color_hex'                   => '#AA33FF',
            'requiere_datos_natales'      => false,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.slug', 'lectura-de-runas');
        $response->assertJsonPath('data.activo', true);

        $this->assertDatabaseHas('tipos_consulta', [
            'nombre' => 'Lectura de Runas',
            'slug'   => 'lectura-de-runas',
        ]);
    }

    public function test_store_rejects_duplicate_nombre(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $existing = TipoConsulta::first();

        $response = $this->postJson('/api/admin/tipos-consulta', [
            'nombre'                      => $existing->nombre,
            'descripcion'                 => 'Duplicado.',
            'duracion_minutos'            => 30,
            'precio_referencial_centavos' => 1000,
            'moneda'                      => 'CLP',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('nombre');
    }

    public function test_store_rejects_invalid_duracion(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/tipos-consulta', [
            'nombre'                      => 'Lectura Rápida',
            'descripcion'                 => 'Demasiado corta.',
            'duracion_minutos'            => 5,
            'precio_referencial_centavos' => 1000,
            'moneda'                      => 'CLP',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('duracion_minutos');
    }

    public function test_store_rejects_negative_precio(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/tipos-consulta', [
            'nombre'                      => 'Servicio Negativo',
            'descripcion'                 => 'Precio inválido.',
            'duracion_minutos'            => 30,
            'precio_referencial_centavos' => -100,
            'moneda'                      => 'CLP',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('precio_referencial_centavos');
    }

    public function test_admin_can_update_tipo_without_changing_slug(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $tipo = TipoConsulta::factory()->create([
            'nombre' => 'Tarot Clásico',
            'slug'   => 'tarot-clasico',
        ]);

        $response = $this->putJson("/api/admin/tipos-consulta/{$tipo->id}", [
            'nombre'                      => 'Tarot Clásico Premium',
            'descripcion'                 => 'Versión premium.',
            'duracion_minutos'            => 90,
            'precio_referencial_centavos' => 5000000,
            'moneda'                      => 'CLP',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.nombre', 'Tarot Clásico Premium');
        $response->assertJsonPath('data.slug', 'tarot-clasico');
    }

    public function test_admin_can_toggle_activo(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $tipo = TipoConsulta::factory()->create(['activo' => true]);

        $response = $this->patchJson("/api/admin/tipos-consulta/{$tipo->id}/toggle");
        $response->assertOk();
        $response->assertJsonPath('data.activo', false);

        $response = $this->patchJson("/api/admin/tipos-consulta/{$tipo->id}/toggle");
        $response->assertOk();
        $response->assertJsonPath('data.activo', true);
    }

    public function test_admin_can_reorder(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $a = TipoConsulta::factory()->create(['orden_visualizacion' => 1]);
        $b = TipoConsulta::factory()->create(['orden_visualizacion' => 2]);
        $c = TipoConsulta::factory()->create(['orden_visualizacion' => 3]);

        $response = $this->patchJson('/api/admin/tipos-consulta/reorder', [
            'ids' => [$c->id, $a->id, $b->id],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('tipos_consulta', ['id' => $c->id, 'orden_visualizacion' => 1]);
        $this->assertDatabaseHas('tipos_consulta', ['id' => $a->id, 'orden_visualizacion' => 2]);
        $this->assertDatabaseHas('tipos_consulta', ['id' => $b->id, 'orden_visualizacion' => 3]);
    }

    public function test_admin_can_upload_imagen(): void
    {
        Storage::fake('local');

        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $tipo = TipoConsulta::factory()->create(['slug' => 'tarot-test']);

        $file = UploadedFile::fake()->image('photo.jpg', 400, 400)->size(500);

        $response = $this->postJson("/api/admin/tipos-consulta/{$tipo->id}/imagen", [
            'imagen' => $file,
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('data.imagen_url'));
        Storage::disk('local')->assertExists('public/tipos-consulta/tarot-test.jpg');
    }

    public function test_admin_can_delete_imagen(): void
    {
        Storage::fake('local');

        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $tipo = TipoConsulta::factory()->create(['slug' => 'tarot-del']);

        $file = UploadedFile::fake()->image('photo.png', 400, 400);
        $this->postJson("/api/admin/tipos-consulta/{$tipo->id}/imagen", ['imagen' => $file]);

        Storage::disk('local')->assertExists('public/tipos-consulta/tarot-del.png');

        $response = $this->deleteJson("/api/admin/tipos-consulta/{$tipo->id}/imagen");
        $response->assertOk();
        $response->assertJsonPath('data.imagen_url', null);

        Storage::disk('local')->assertMissing('public/tipos-consulta/tarot-del.png');
    }

    private function makeUserWithRole(string $roleNombre): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->profile()->create([
            'nombre' => 'Usuario',
        ]);

        $user->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', $roleNombre)->value('id') => ['asignado_en' => now()],
        ]);

        return $user;
    }
}
