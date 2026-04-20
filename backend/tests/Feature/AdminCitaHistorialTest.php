<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\DailyWebhookEvent;
use App\Models\Grabacion;
use App\Models\Resumen;
use App\Models\Role;
use App\Models\TipoConsulta;
use App\Models\Transcripcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCitaHistorialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_non_admin_user_cannot_access_admin_cita_historial(): void
    {
        $cliente = $this->makeUserWithRole('cliente');

        Sanctum::actingAs($cliente);

        $this->getJson('/api/admin/citas/historial')
            ->assertStatus(403)
            ->assertJsonPath('message', 'No autorizado para consultar historial administrativo.');
    }

    public function test_admin_can_list_cita_historial_with_filters(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        $clienteA = $this->makeUserWithRole('cliente', 'clientea@test.com');
        $clienteB = $this->makeUserWithRole('cliente', 'clienteb@test.com');

        $citaA = $this->createCitaForClient($clienteA, 'TE-ADM-HIST1', 'resumen_completado');
        $citaB = $this->createCitaForClient($clienteB, 'TE-ADM-HIST2', 'confirmada');

        $this->attachHistorial($citaA);
        $this->attachHistorial($citaB);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/citas/historial?estado=resumen_completado&cliente_email=clientea@test.com&per_page=10');

        $response
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.codigo_referencia', 'TE-ADM-HIST1')
            ->assertJsonPath('data.0.cliente.email', 'clientea@test.com');
    }

    public function test_admin_can_export_cita_historial_csv_with_filters(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        $clienteA = $this->makeUserWithRole('cliente', 'clientea@test.com');
        $clienteB = $this->makeUserWithRole('cliente', 'clienteb@test.com');

        $citaA = $this->createCitaForClient($clienteA, 'TE-ADM-HIST1', 'resumen_completado');
        $citaB = $this->createCitaForClient($clienteB, 'TE-ADM-HIST2', 'confirmada');

        $this->attachHistorial($citaA);
        $this->attachHistorial($citaB);

        Sanctum::actingAs($admin);

        $response = $this->get('/api/admin/citas/historial/export?estado=resumen_completado&cliente_email=clientea@test.com');

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $contentDisposition = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString('attachment; filename="admin-citas-historial-', $contentDisposition);

        $content = $response->getContent();
        $this->assertStringContainsString('uuid,codigo_referencia,estado,inicio_utc,fin_utc,cliente_email,cliente_nombre,tipo_consulta_slug,tipo_consulta_nombre,total_grabaciones,total_transcripciones,total_resumenes', $content);
        $this->assertStringContainsString('TE-ADM-HIST1', $content);
        $this->assertStringContainsString('clientea@test.com', $content);
        $this->assertStringNotContainsString('TE-ADM-HIST2', $content);
        $this->assertStringNotContainsString('clienteb@test.com', $content);
    }

    public function test_non_admin_user_cannot_export_admin_cita_historial(): void
    {
        $cliente = $this->makeUserWithRole('cliente');

        Sanctum::actingAs($cliente);

        $this->get('/api/admin/citas/historial/export')
            ->assertStatus(403)
            ->assertJsonPath('message', 'No autorizado para exportar historial administrativo.');
    }

    public function test_unauthenticated_user_cannot_export_admin_cita_historial(): void
    {
        $this->getJson('/api/admin/citas/historial/export')
            ->assertStatus(401);
    }

    private function makeUserWithRole(string $roleName, ?string $email = null): User
    {
        $user = User::factory()->create([
            'email' => $email ?? ('user_'.Str::lower(Str::random(6)).'@test.com'),
            'email_verified_at' => now(),
        ]);

        $user->profile()->create([
            'nombre' => 'Usuario '.Str::upper(Str::random(4)),
        ]);

        $user->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', $roleName)->value('id') => ['asignado_en' => now()],
        ]);

        return $user;
    }

    private function createCitaForClient(User $cliente, string $codigoReferencia, string $estado): Cita
    {
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        return Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => $codigoReferencia,
            'cliente_id' => $cliente->id,
            'especialista_id' => null,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => now()->addDay(),
            'fin_utc' => now()->addDay()->addMinutes(120),
            'duracion_minutos' => 120,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => $estado,
            'canal_pago' => 'stripe',
            'precio_total_centavos' => 50000,
            'precio_final_centavos' => 50000,
            'moneda' => 'CLP',
            'es_primera_consulta' => false,
        ]);
    }

    private function attachHistorial(Cita $cita): void
    {
        $event = DailyWebhookEvent::query()->create([
            'event_id' => (string) Str::uuid(),
            'event_type' => 'recording.ready',
            'payload' => ['source' => 'test'],
            'processed_at' => now(),
        ]);

        $grabacion = Grabacion::query()->create([
            'cita_id' => $cita->id,
            'daily_webhook_event_id' => $event->id,
            'daily_recording_id' => 'rec_admin_hist_'.Str::lower(Str::random(5)),
            'daily_room_name' => 'room_admin_hist',
            'url_grabacion' => 'https://r2.example.com/recordings/admin-historial.mp4',
            'estado' => $cita->estado,
            'transcripcion_procesada_en' => now(),
            'resumen_generado_en' => now(),
            'metadata' => ['test' => true],
        ]);

        $transcripcion = Transcripcion::query()->create([
            'cita_id' => $cita->id,
            'grabacion_id' => $grabacion->id,
            'contenido' => 'Transcripcion admin historial.',
            'proveedor' => 'openai',
            'modelo' => 'whisper-1',
            'idioma' => 'es',
            'metadata' => ['test' => true],
        ]);

        Resumen::query()->create([
            'cita_id' => $cita->id,
            'grabacion_id' => $grabacion->id,
            'transcripcion_id' => $transcripcion->id,
            'contenido' => 'Resumen admin historial.',
            'proveedor' => 'anthropic',
            'modelo' => 'claude-3-5-haiku-latest',
            'metadata' => ['test' => true],
        ]);
    }
}
