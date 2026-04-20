<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Role;
use App\Models\TipoConsulta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeConsultasPdfExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_unauthenticated_user_cannot_export_consultas_pdf(): void
    {
        $this->getJson('/api/me/consultas/export/pdf')->assertStatus(401);
    }

    public function test_authenticated_user_can_export_consultas_pdf(): void
    {
        $user = $this->makeAuthenticatedClient();
        Sanctum::actingAs($user);

        $this->createCitaForClient($user, 'TE-PDF-EXP1');

        $response = $this->get('/api/me/consultas/export/pdf');

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString('attachment; filename="consultas-', $disposition);

        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertGreaterThan(500, strlen($content));
    }

    private function makeAuthenticatedClient(): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->profile()->create([
            'nombre' => 'Cliente PDF',
        ]);

        $user->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'cliente')->value('id') => ['asignado_en' => now()],
        ]);

        return $user;
    }

    private function createCitaForClient(User $cliente, string $codigoReferencia): Cita
    {
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        return Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => $codigoReferencia,
            'cliente_id' => $cliente->id,
            'especialista_id' => null,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => now()->addDay()->setTime(15, 0, 0),
            'fin_utc' => now()->addDay()->setTime(17, 0, 0),
            'duracion_minutos' => 120,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => 'reservada',
            'canal_pago' => 'stripe',
            'precio_total_centavos' => 50000,
            'precio_final_centavos' => 50000,
            'moneda' => 'CLP',
            'es_primera_consulta' => false,
        ]);
    }
}
