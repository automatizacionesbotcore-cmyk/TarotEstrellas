<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\DailyWebhookEvent;
use App\Models\Grabacion;
use App\Models\Resumen;
use App\Models\TipoConsulta;
use App\Models\Transcripcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeConsultasDetalleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_consulta_detalle_endpoints_require_authentication(): void
    {
        $uuid = (string) Str::uuid();

        $this->getJson('/api/me/consultas/'.$uuid.'/transcripcion')->assertStatus(401);
        $this->getJson('/api/me/consultas/'.$uuid.'/resumen')->assertStatus(401);
        $this->getJson('/api/me/consultas/'.$uuid.'/grabacion-url')->assertStatus(401);
        $this->patchJson('/api/me/consultas/'.$uuid.'/notas-privadas', [
            'notas_privadas' => 'nota',
        ])->assertStatus(401);
    }

    public function test_owner_can_read_consulta_transcripcion_resumen_and_grabacion_url(): void
    {
        $cliente = User::factory()->create(['email_verified_at' => now()]);
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        $cita = Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-ME-DETALLE',
            'cliente_id' => $cliente->id,
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

        $event = DailyWebhookEvent::query()->create([
            'event_id' => (string) Str::uuid(),
            'event_type' => 'recording.ready',
            'payload' => ['source' => 'test'],
            'processed_at' => now(),
        ]);

        $grabacion = Grabacion::query()->create([
            'uuid' => (string) Str::uuid(),
            'cita_id' => $cita->id,
            'daily_webhook_event_id' => $event->id,
            'daily_recording_id' => 'rec_detalle_'.Str::lower(Str::random(4)),
            'daily_room_name' => 'room_me_detalle',
            'url_grabacion' => 'https://r2.example.com/recordings/me-detalle.mp4',
            'estado' => 'resumen_completado',
        ]);

        $transcripcion = Transcripcion::query()->create([
            'cita_id' => $cita->id,
            'grabacion_id' => $grabacion->id,
            'contenido' => 'Transcripcion detalle cliente.',
            'proveedor' => 'openai',
            'modelo' => 'whisper-1',
            'idioma' => 'es',
        ]);

        Resumen::query()->create([
            'cita_id' => $cita->id,
            'grabacion_id' => $grabacion->id,
            'transcripcion_id' => $transcripcion->id,
            'contenido' => 'Resumen detalle cliente.',
            'proveedor' => 'anthropic',
            'modelo' => 'claude-3-5-haiku-latest',
        ]);

        Sanctum::actingAs($cliente);

        $this->getJson('/api/me/consultas')
            ->assertOk()
            ->assertJsonPath('data.0.uuid', $cita->uuid);

        $this->getJson('/api/me/consultas/'.$cita->uuid)
            ->assertOk()
            ->assertJsonPath('data.uuid', $cita->uuid);

        $this->getJson('/api/me/consultas/'.$cita->uuid.'/transcripcion')
            ->assertStatus(403);

        $this->getJson('/api/me/consultas/'.$cita->uuid.'/resumen')
            ->assertOk()
            ->assertJsonPath('data.resumen.contenido', 'Resumen detalle cliente.');

        $this->getJson('/api/me/consultas/'.$cita->uuid.'/grabacion-url')
            ->assertOk()
            ->assertJsonPath('data.url', 'https://r2.example.com/recordings/me-detalle.mp4')
            ->assertJsonPath('data.cita_uuid', $cita->uuid);
    }

    public function test_owner_can_update_notas_privadas_and_other_user_cannot_access_consulta(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $otherUser = User::factory()->create(['email_verified_at' => now()]);
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        $cita = Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-ME-NOTAS',
            'cliente_id' => $owner->id,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => now()->addDay(),
            'fin_utc' => now()->addDay()->addMinutes(120),
            'duracion_minutos' => 120,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => 'confirmada',
            'canal_pago' => 'stripe',
            'precio_total_centavos' => 50000,
            'precio_final_centavos' => 50000,
            'moneda' => 'CLP',
            'es_primera_consulta' => false,
        ]);

        Sanctum::actingAs($owner);

        $this->patchJson('/api/me/consultas/'.$cita->uuid.'/notas-privadas', [
            'notas_privadas' => 'Notas privadas del cliente para su seguimiento.',
        ])
            ->assertOk()
            ->assertJsonPath('data.notas_privadas', 'Notas privadas del cliente para su seguimiento.');

        $this->assertDatabaseHas('citas', [
            'id' => $cita->id,
            'notas_cliente' => 'Notas privadas del cliente para su seguimiento.',
        ]);

        Sanctum::actingAs($otherUser);

        $this->getJson('/api/me/consultas/'.$cita->uuid.'/resumen')
            ->assertStatus(404);
    }
}
