<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Grabacion;
use App\Models\Resumen;
use App\Models\Role;
use App\Models\TipoConsulta;
use App\Models\Transcripcion;
use App\Models\User;
use App\Models\DailyWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgenteIATest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        config([
            'services.anthropic.api_key' => 'test-key',
            'services.anthropic.agente_model' => 'claude-3-5-sonnet-latest',
            'services.anthropic.agente_max_sesiones' => 20,
        ]);
    }

    public function test_publico_endpoint_no_requires_auth(): void
    {
        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'content' => [['type' => 'text', 'text' => 'TarotEstrellas es una plataforma de consultas esotericas.']],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 30],
            ], 200),
        ]);

        $response = $this->postJson('/api/agente/publico', ['pregunta' => '¿Que es TarotEstrellas?']);

        $response->assertStatus(201)
            ->assertJsonPath('autor_rol', 'publico')
            ->assertJsonPath('cliente_id', null);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->postJson('/api/me/agente/consultar', ['pregunta' => 'Hola'])
            ->assertStatus(401);
    }

    public function test_admin_can_query_about_any_client(): void
    {
        $admin = $this->makeUser('admin_especialista');
        $cliente = $this->makeUser('cliente');
        $this->seedSesionResumida($cliente, 'Tema amor');

        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'content' => [['type' => 'text', 'text' => 'El cliente consulta recurrentemente sobre amor.']],
                'usage' => ['input_tokens' => 1500, 'output_tokens' => 50],
            ], 200),
        ]);

        Sanctum::actingAs($admin);

        $resp = $this->postJson('/api/agente/consultar', [
            'cliente_uuid' => $cliente->uuid,
            'pregunta' => 'Que temas trae este cliente?',
        ]);

        $resp->assertStatus(201)
            ->assertJsonPath('autor_rol', 'admin')
            ->assertJsonPath('respuesta', 'El cliente consulta recurrentemente sobre amor.')
            ->assertJsonPath('tokens_in', 1500)
            ->assertJsonPath('tokens_out', 50);

        $this->assertDatabaseHas('agente_conversaciones', [
            'cliente_id' => $cliente->id,
            'autor_user_id' => $admin->id,
            'autor_rol' => 'admin',
            'modelo' => 'claude-3-5-sonnet-latest',
        ]);
    }

    public function test_non_admin_cannot_use_admin_endpoint(): void
    {
        $cliente = $this->makeUser('cliente');
        $otro = $this->makeUser('cliente', 'otro@test.com');

        Sanctum::actingAs($cliente);

        $this->postJson('/api/agente/consultar', [
            'cliente_uuid' => $otro->uuid,
            'pregunta' => 'Pregunta?',
        ])->assertStatus(403);
    }

    public function test_client_can_query_self_history(): void
    {
        $cliente = $this->makeUser('cliente');
        $this->seedSesionResumida($cliente, 'Tema trabajo');

        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Has consultado sobre trabajo recientemente.']],
                'usage' => ['input_tokens' => 800, 'output_tokens' => 30],
            ], 200),
        ]);

        Sanctum::actingAs($cliente);

        $this->postJson('/api/me/agente/consultar', [
            'pregunta' => 'Que vi en mi ultima sesion?',
        ])->assertStatus(201)
            ->assertJsonPath('autor_rol', 'cliente')
            ->assertJsonPath('respuesta', 'Has consultado sobre trabajo recientemente.');

        $this->assertDatabaseHas('agente_conversaciones', [
            'cliente_id' => $cliente->id,
            'autor_user_id' => $cliente->id,
            'autor_rol' => 'cliente',
        ]);
    }

    public function test_validation_rejects_short_pregunta(): void
    {
        $cliente = $this->makeUser('cliente');
        Sanctum::actingAs($cliente);

        $this->postJson('/api/me/agente/consultar', ['pregunta' => 'a'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pregunta']);
    }

    public function test_returns_502_on_anthropic_error_and_persists_row(): void
    {
        $admin = $this->makeUser('admin_especialista');
        $cliente = $this->makeUser('cliente');

        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response(['error' => 'rate limit'], 429),
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/agente/consultar', [
            'cliente_uuid' => $cliente->uuid,
            'pregunta' => 'Pregunta cualquiera',
        ])->assertStatus(502);

        $this->assertDatabaseHas('agente_conversaciones', [
            'cliente_id' => $cliente->id,
            'autor_rol' => 'admin',
        ]);
        $row = \App\Models\AgenteConversacion::query()->where('cliente_id', $cliente->id)->first();
        $this->assertNotNull($row->error);
        $this->assertNull($row->respuesta);
    }

    public function test_works_when_client_has_no_sessions(): void
    {
        $cliente = $this->makeUser('cliente');

        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'content' => [['type' => 'text', 'text' => 'No tengo informacion previa de tu historial.']],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 20],
            ], 200),
        ]);

        Sanctum::actingAs($cliente);

        $this->postJson('/api/me/agente/consultar', [
            'pregunta' => 'Que recuerdas de mi?',
        ])->assertStatus(201)
            ->assertJsonPath('contexto_sesiones', 0);
    }

    public function test_returns_502_when_api_key_missing(): void
    {
        config(['services.anthropic.api_key' => '']);

        $cliente = $this->makeUser('cliente');
        Sanctum::actingAs($cliente);

        $this->postJson('/api/me/agente/consultar', [
            'pregunta' => 'Hola agente',
        ])->assertStatus(502);
    }

    public function test_admin_can_list_conversations(): void
    {
        $admin = $this->makeUser('admin_especialista');
        $cliente = $this->makeUser('cliente');

        \App\Models\AgenteConversacion::query()->create([
            'cliente_id' => $cliente->id,
            'autor_user_id' => $admin->id,
            'autor_rol' => 'admin',
            'pregunta' => 'q',
            'respuesta' => 'r',
            'modelo' => 'claude',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/agente/conversaciones?cliente_uuid='.$cliente->uuid)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.autor_rol', 'admin');
    }

    public function test_client_can_list_self_conversations(): void
    {
        $cliente = $this->makeUser('cliente');

        \App\Models\AgenteConversacion::query()->create([
            'cliente_id' => $cliente->id,
            'autor_user_id' => $cliente->id,
            'autor_rol' => 'cliente',
            'pregunta' => 'q',
            'respuesta' => 'r',
            'modelo' => 'claude',
        ]);

        Sanctum::actingAs($cliente);

        $this->getJson('/api/me/agente/conversaciones')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    private function makeUser(string $roleName, ?string $email = null): User
    {
        $user = User::factory()->create([
            'email' => $email ?? ('user_'.Str::lower(Str::random(6)).'@test.com'),
            'email_verified_at' => now(),
        ]);
        $user->profile()->create(['nombre' => 'U '.Str::upper(Str::random(4))]);
        $user->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', $roleName)->value('id') => ['asignado_en' => now()],
        ]);
        return $user;
    }

    private function seedSesionResumida(User $cliente, string $tema): void
    {
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        $cita = Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-AG-'.Str::upper(Str::random(4)),
            'cliente_id' => $cliente->id,
            'especialista_id' => null,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => now()->subDays(3),
            'fin_utc' => now()->subDays(3)->addHour(),
            'duracion_minutos' => 60,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => 'resumen_completado',
            'canal_pago' => 'stripe',
            'precio_total_centavos' => 50000,
            'precio_final_centavos' => 50000,
            'moneda' => 'CLP',
            'tema_principal' => $tema,
            'es_primera_consulta' => false,
        ]);

        $event = DailyWebhookEvent::query()->create([
            'event_id' => (string) Str::uuid(),
            'event_type' => 'recording.ready',
            'payload' => ['source' => 'test'],
            'processed_at' => now(),
        ]);

        $grab = Grabacion::query()->create([
            'cita_id' => $cita->id,
            'daily_webhook_event_id' => $event->id,
            'daily_recording_id' => 'rec_'.Str::lower(Str::random(6)),
            'daily_room_name' => 'room_ag',
            'url_grabacion' => 'https://r2.example.com/x.mp4',
            'estado' => 'resumen_completado',
            'metadata' => ['test' => true],
        ]);

        $tr = Transcripcion::query()->create([
            'cita_id' => $cita->id,
            'grabacion_id' => $grab->id,
            'contenido' => 'Transcripcion test sobre '.$tema,
            'proveedor' => 'openai',
            'modelo' => 'whisper-1',
            'idioma' => 'es',
            'metadata' => [],
        ]);

        Resumen::query()->create([
            'cita_id' => $cita->id,
            'grabacion_id' => $grab->id,
            'transcripcion_id' => $tr->id,
            'contenido' => 'Resumen breve sobre '.$tema,
            'proveedor' => 'anthropic',
            'modelo' => 'claude-3-5-haiku-latest',
            'metadata' => [],
        ]);
    }
}
