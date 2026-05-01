<?php

namespace Tests\Feature;

use App\Models\Paquete;
use App\Models\PlantillaNotificacion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class B567AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_paquete_admin_crud(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $resp = $this->postJson('/api/admin/paquetes', [
            'slug' => 'pack-test', 'nombre' => 'Pack Test', 'tipo' => 'paquete',
            'consultas_incluidas' => 3, 'vigencia_dias' => 90,
            'precio_centavos' => 9000, 'moneda' => 'CLP',
        ])->assertCreated();
        $id = $resp->json('data.id');

        $this->getJson('/api/admin/paquetes')->assertOk();
        $this->patchJson("/api/admin/paquetes/$id", ['nombre' => 'Pack 2', 'slug' => 'pack-test', 'tipo' => 'paquete',
            'consultas_incluidas' => 3, 'vigencia_dias' => 90, 'precio_centavos' => 12000, 'moneda' => 'CLP'])->assertOk();
        $this->postJson("/api/admin/paquetes/$id/toggle")->assertOk();
        $this->deleteJson("/api/admin/paquetes/$id")->assertNoContent();
    }

    public function test_plantilla_admin_crud_y_versiones(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $r = $this->postJson('/api/admin/plantillas', [
            'codigo' => 'recordatorio_24h', 'canal' => 'email',
            'nombre' => 'Recordatorio 24h', 'asunto' => 'Tu cita {{cliente_nombre}}',
            'cuerpo' => 'Hola {{cliente_nombre}}, recuerda tu cita.',
        ])->assertCreated();
        $id = $r->json('data.id');

        $this->patchJson("/api/admin/plantillas/$id", [
            'codigo' => 'recordatorio_24h', 'canal' => 'email',
            'nombre' => 'Recordatorio 24h', 'asunto' => 'Tu cita',
            'cuerpo' => 'Hola, recuerda tu cita mañana.',
        ])->assertOk();

        $show = $this->getJson("/api/admin/plantillas/$id")->assertOk();
        $this->assertGreaterThanOrEqual(2, count($show->json('data.versiones')));

        $prev = $this->postJson("/api/admin/plantillas/$id/preview", [
            'variables' => ['cliente_nombre' => 'Ada'],
        ])->assertOk();
        $this->assertStringContainsString('Hola', $prev->json('data.cuerpo'));
    }

    public function test_audit_log_index_admin(): void
    {
        Sanctum::actingAs($this->makeAdmin());
        $this->getJson('/api/admin/audit-log')->assertOk();
    }

    public function test_notificaciones_index_admin(): void
    {
        Sanctum::actingAs($this->makeAdmin());
        $this->getJson('/api/admin/notificaciones')->assertOk()->assertJsonStructure(['data']);
    }

    public function test_reportes_export_csv(): void
    {
        Sanctum::actingAs($this->makeAdmin());
        $resp = $this->get('/api/admin/reportes/export?tipo=ingresos')->assertOk();
        $this->assertStringContainsString('text/csv', $resp->headers->get('Content-Type'));
    }

    public function test_moneda_detectar_y_convertir(): void
    {
        $this->getJson('/api/moneda/detectar', ['CF-IPCountry' => 'CL'])
            ->assertOk()->assertJsonPath('data.moneda', 'CLP');

        $this->postJson('/api/moneda/convertir', [
            'centavos' => 100000, 'origen' => 'USD', 'destino' => 'CLP',
        ])->assertOk()->assertJsonStructure(['data' => ['centavos']]);
    }

    public function test_clientes_filtro_membresia_activa(): void
    {
        Sanctum::actingAs($this->makeAdmin());
        $this->getJson('/api/admin/clientes?membresia_activa=1')->assertOk();
        $this->getJson('/api/admin/clientes?inactivo_meses=3')->assertOk();
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

