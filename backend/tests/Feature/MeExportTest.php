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

class MeExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_unauthenticated_user_cannot_access_me_export_endpoints(): void
    {
        $this->getJson('/api/me/export')->assertStatus(401);
        $this->getJson('/api/me/datos-personales')->assertStatus(401);
    }

    public function test_authenticated_user_can_export_personal_data_and_history_json(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->profile()->create([
            'nombre' => 'Cliente Export',
        ]);

        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        $cita = Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-ME-EXPORT',
            'cliente_id' => $user->id,
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

        $event = DailyWebhookEvent::query()->create([
            'event_id' => (string) Str::uuid(),
            'event_type' => 'recording.ready',
            'payload' => ['source' => 'test'],
            'processed_at' => now(),
        ]);

        $grabacion = Grabacion::query()->create([
            'cita_id' => $cita->id,
            'daily_webhook_event_id' => $event->id,
            'daily_recording_id' => 'rec_me_export_'.Str::lower(Str::random(4)),
            'daily_room_name' => 'room_me_export',
            'url_grabacion' => 'https://r2.example.com/recordings/me-export.mp4',
            'estado' => 'resumen_completado',
            'transcripcion_procesada_en' => now(),
            'resumen_generado_en' => now(),
            'metadata' => ['test' => true],
        ]);

        $transcripcion = Transcripcion::query()->create([
            'cita_id' => $cita->id,
            'grabacion_id' => $grabacion->id,
            'contenido' => 'Contenido de transcripcion para export personal.',
            'proveedor' => 'openai',
            'modelo' => 'whisper-1',
            'idioma' => 'es',
            'metadata' => ['test' => true],
        ]);

        Resumen::query()->create([
            'cita_id' => $cita->id,
            'grabacion_id' => $grabacion->id,
            'transcripcion_id' => $transcripcion->id,
            'contenido' => 'Resumen de prueba para export personal.',
            'proveedor' => 'anthropic',
            'modelo' => 'claude-3-5-haiku-latest',
            'metadata' => ['test' => true],
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me/export');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'exportado_en',
                'data' => [
                    'usuario',
                    'perfil',
                    'dato_natal',
                    'preferencias_notificacion',
                    'consentimientos',
                    'citas',
                ],
            ])
            ->assertJsonPath('data.usuario.email', $user->email)
            ->assertJsonPath('data.citas.0.codigo_referencia', 'TE-ME-EXPORT')
            ->assertJsonPath('data.citas.0.historial.0.transcripcion.contenido', 'Contenido de transcripcion para export personal.')
            ->assertJsonPath('data.citas.0.historial.0.resumen.contenido', 'Resumen de prueba para export personal.');
    }

    public function test_datos_personales_endpoint_returns_same_contract(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->profile()->create([
            'nombre' => 'Cliente Datos Personales',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/me/datos-personales')
            ->assertOk()
            ->assertJsonPath('data.usuario.email', $user->email)
            ->assertJsonStructure([
                'exportado_en',
                'data' => ['usuario', 'perfil', 'citas'],
            ]);
    }
}
