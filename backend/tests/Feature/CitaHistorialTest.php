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

class CitaHistorialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_client_can_fetch_historial_for_own_cita(): void
    {
        $cliente = $this->makeAuthenticatedClient();
        Sanctum::actingAs($cliente);

        $cita = $this->createCitaForClient($cliente, 'TE-HIST-OWN1');
        $this->attachHistorial($cita);

        $response = $this->getJson('/api/citas/'.$cita->uuid.'/historial');

        $response
            ->assertOk()
            ->assertJsonPath('data.cita.uuid', $cita->uuid)
            ->assertJsonPath('data.historial.0.grabacion.estado', 'resumen_completado')
            ->assertJsonPath('data.historial.0.transcripcion.idioma', 'es')
            ->assertJsonPath('data.historial.0.resumen.proveedor', 'anthropic');
    }

    public function test_client_cannot_fetch_historial_for_another_clients_cita(): void
    {
        $clienteA = $this->makeAuthenticatedClient();
        $clienteB = $this->makeAuthenticatedClient();

        $citaDeB = $this->createCitaForClient($clienteB, 'TE-HIST-OTR1');

        Sanctum::actingAs($clienteA);

        $this->getJson('/api/citas/'.$citaDeB->uuid.'/historial')
            ->assertNotFound();
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

    private function createCitaForClient(User $cliente, string $codigoReferencia): Cita
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
            'estado' => 'resumen_completado',
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
            'daily_recording_id' => 'rec_hist_123',
            'daily_room_name' => 'room_hist_1',
            'url_grabacion' => 'https://r2.example.com/recordings/historial.mp4',
            'estado' => 'resumen_completado',
            'transcripcion_procesada_en' => now(),
            'resumen_generado_en' => now(),
            'metadata' => ['test' => true],
        ]);

        $transcripcion = Transcripcion::query()->create([
            'cita_id' => $cita->id,
            'grabacion_id' => $grabacion->id,
            'contenido' => 'Transcripcion para historial.',
            'proveedor' => 'openai',
            'modelo' => 'whisper-1',
            'idioma' => 'es',
            'metadata' => ['test' => true],
        ]);

        Resumen::query()->create([
            'cita_id' => $cita->id,
            'grabacion_id' => $grabacion->id,
            'transcripcion_id' => $transcripcion->id,
            'contenido' => 'Resumen para historial.',
            'proveedor' => 'anthropic',
            'modelo' => 'claude-3-5-haiku-latest',
            'metadata' => ['test' => true],
        ]);
    }
}
