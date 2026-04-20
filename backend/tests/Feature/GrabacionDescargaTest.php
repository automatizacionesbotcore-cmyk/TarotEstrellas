<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\DailyWebhookEvent;
use App\Models\Grabacion;
use App\Models\Role;
use App\Models\TipoConsulta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GrabacionDescargaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_unauthenticated_user_cannot_register_download(): void
    {
        $this->postJson('/api/grabaciones/'.Str::uuid().'/descarga')
            ->assertStatus(401);

        $this->getJson('/api/grabaciones/'.Str::uuid().'/url')
            ->assertStatus(401);
    }

    public function test_owner_can_register_download_and_counter_increments(): void
    {
        $cliente = $this->makeAuthenticatedClient();
        Sanctum::actingAs($cliente);

        $grabacion = $this->createGrabacionForClient($cliente);

        $this->postJson('/api/grabaciones/'.$grabacion->uuid.'/descarga')
            ->assertOk()
            ->assertJsonPath('data.grabacion_uuid', $grabacion->uuid)
            ->assertJsonPath('data.descargada_por_cliente', true)
            ->assertJsonPath('data.total_descargas', 1);

        $this->postJson('/api/grabaciones/'.$grabacion->uuid.'/descarga')
            ->assertOk()
            ->assertJsonPath('data.total_descargas', 2);

        $this->assertDatabaseHas('grabaciones', [
            'id' => $grabacion->id,
            'descargada_por_cliente' => 1,
            'total_descargas' => 2,
        ]);
    }

    public function test_other_client_cannot_register_download_for_foreign_recording(): void
    {
        $clienteA = $this->makeAuthenticatedClient();
        $clienteB = $this->makeAuthenticatedClient();
        $grabacion = $this->createGrabacionForClient($clienteB);

        Sanctum::actingAs($clienteA);

        $this->postJson('/api/grabaciones/'.$grabacion->uuid.'/descarga')
            ->assertStatus(403);

        $this->getJson('/api/grabaciones/'.$grabacion->uuid.'/url')
            ->assertStatus(403);
    }

    public function test_owner_can_get_temporary_recording_url(): void
    {
        $cliente = $this->makeAuthenticatedClient();
        Sanctum::actingAs($cliente);

        $grabacion = $this->createGrabacionForClient($cliente);

        $response = $this->getJson('/api/grabaciones/'.$grabacion->uuid.'/url')
            ->assertOk()
            ->assertJsonPath('data.grabacion_uuid', $grabacion->uuid)
            ->assertJsonPath('data.cita_uuid', $grabacion->cita->uuid)
            ->assertJsonStructure([
                'data' => ['grabacion_uuid', 'cita_uuid', 'url', 'expires_at'],
            ]);

        $payload = $response->json('data');
        $this->assertIsArray($payload);
        $this->assertIsString($payload['url']);
        $this->assertIsString($payload['expires_at']);
        $this->assertStringContainsString('expires=', $payload['url']);
        $this->assertNotSame('', $payload['expires_at']);
    }

    private function makeAuthenticatedClient(): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->profile()->create([
            'nombre' => 'Cliente',
        ]);

        $user->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'cliente')->value('id') => ['asignado_en' => now()],
        ]);

        return $user;
    }

    private function createGrabacionForClient(User $cliente): Grabacion
    {
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        $cita = Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-REC-'.Str::upper(Str::random(4)),
            'cliente_id' => $cliente->id,
            'especialista_id' => null,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => now()->addDay(),
            'fin_utc' => now()->addDay()->addMinutes(60),
            'duracion_minutos' => 60,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => 'resumen_completado',
            'canal_pago' => 'stripe',
            'precio_total_centavos' => 4500000,
            'precio_final_centavos' => 4500000,
            'moneda' => 'CLP',
            'es_primera_consulta' => false,
        ]);

        $event = DailyWebhookEvent::query()->create([
            'event_id' => (string) Str::uuid(),
            'event_type' => 'recording.ready',
            'payload' => ['source' => 'test'],
            'processed_at' => now(),
        ]);

        return Grabacion::query()->create([
            'uuid' => (string) Str::uuid(),
            'cita_id' => $cita->id,
            'daily_webhook_event_id' => $event->id,
            'daily_recording_id' => 'rec_'.Str::lower(Str::random(8)),
            'daily_room_name' => 'room_'.Str::lower(Str::random(6)),
            'url_grabacion' => 'https://r2.example.com/recordings/test.mp4',
            'estado' => 'resumen_completado',
            'metadata' => ['test' => true],
        ]);
    }
}
