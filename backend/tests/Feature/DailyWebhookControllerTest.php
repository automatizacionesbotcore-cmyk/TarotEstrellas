<?php

namespace Tests\Feature;

use App\Jobs\ProcesarTranscripcionJob;
use App\Models\Cita;
use App\Models\TipoConsulta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class DailyWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_daily_webhook_rejects_invalid_signature(): void
    {
        config(['services.daily.webhook_secret' => 'daily_test_secret']);

        $payload = json_encode([
            'id' => 'evt_daily_invalid',
            'type' => 'recording.ready',
        ], JSON_THROW_ON_ERROR);

        $this->call('POST', '/api/webhooks/daily', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_DAILY_SIGNATURE' => 'invalid-signature',
        ], $payload)->assertStatus(400);
    }

    public function test_daily_webhook_accepts_valid_signature_and_is_idempotent(): void
    {
        config(['services.daily.webhook_secret' => 'daily_test_secret']);
        Queue::fake();

        $cliente = User::factory()->create();
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        $cita = Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-DAIL-YWEB',
            'cliente_id' => $cliente->id,
            'especialista_id' => null,
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

        $payload = json_encode([
            'id' => 'evt_daily_123',
            'type' => 'recording.ready',
            'data' => [
                'room' => 'room_1',
                'recording_id' => 'rec_123',
                'download_url' => 'https://r2.example.com/recordings/rec_123.mp4',
                'metadata' => [
                    'cita_uuid' => $cita->uuid,
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $payload, 'daily_test_secret');

        $this->call('POST', '/api/webhooks/daily', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_DAILY_SIGNATURE' => $signature,
        ], $payload)
            ->assertOk()
            ->assertJsonPath('received', true)
            ->assertJsonPath('event_type', 'recording.ready');

        $this->call('POST', '/api/webhooks/daily', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_DAILY_SIGNATURE' => $signature,
        ], $payload)
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $this->assertDatabaseHas('daily_webhook_events', [
            'event_id' => 'evt_daily_123',
            'event_type' => 'recording.ready',
        ]);

        $this->assertDatabaseHas('grabaciones', [
            'cita_id' => $cita->id,
            'daily_recording_id' => 'rec_123',
            'daily_room_name' => 'room_1',
            'estado' => 'pendiente_transcripcion',
        ]);

        $this->assertDatabaseCount('daily_webhook_events', 1);
        $this->assertDatabaseCount('grabaciones', 1);
        Queue::assertPushed(ProcesarTranscripcionJob::class, 1);
    }

    public function test_daily_webhook_recording_error_updates_grabacion_state_without_dispatching_transcription(): void
    {
        config(['services.daily.webhook_secret' => 'daily_test_secret']);
        Queue::fake();

        $cliente = User::factory()->create();
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        $cita = Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-DAIL-ERRR',
            'cliente_id' => $cliente->id,
            'especialista_id' => null,
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

        $payload = json_encode([
            'id' => 'evt_daily_error_1',
            'type' => 'recording.error',
            'data' => [
                'room' => 'room_error_1',
                'recording_id' => 'rec_error_1',
                'metadata' => [
                    'cita_uuid' => $cita->uuid,
                ],
                'error' => 'recording_failed',
            ],
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $payload, 'daily_test_secret');

        $this->call('POST', '/api/webhooks/daily', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_DAILY_SIGNATURE' => $signature,
        ], $payload)
            ->assertOk()
            ->assertJsonPath('received', true)
            ->assertJsonPath('event_type', 'recording.error');

        $this->assertDatabaseHas('grabaciones', [
            'cita_id' => $cita->id,
            'daily_recording_id' => 'rec_error_1',
            'daily_room_name' => 'room_error_1',
            'estado' => 'error_grabacion',
        ]);

        Queue::assertNotPushed(ProcesarTranscripcionJob::class);
    }

    public function test_daily_webhook_meeting_ended_finalizes_cita(): void
    {
        config(['services.daily.webhook_secret' => 'daily_test_secret']);
        Queue::fake();

        $cliente = User::factory()->create();
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        $cita = Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-DAIL-ENDD',
            'cliente_id' => $cliente->id,
            'especialista_id' => null,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => now()->subHour(),
            'fin_utc' => now(),
            'duracion_minutos' => 60,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => 'en_curso',
            'canal_pago' => 'stripe',
            'precio_total_centavos' => 50000,
            'precio_final_centavos' => 50000,
            'moneda' => 'CLP',
            'es_primera_consulta' => false,
        ]);

        $payload = json_encode([
            'id' => 'evt_daily_ended_1',
            'type' => 'meeting.ended',
            'data' => [
                'room' => 'room_ended_1',
                'metadata' => [
                    'cita_uuid' => $cita->uuid,
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $payload, 'daily_test_secret');

        $this->call('POST', '/api/webhooks/daily', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_DAILY_SIGNATURE' => $signature,
        ], $payload)
            ->assertOk()
            ->assertJsonPath('received', true)
            ->assertJsonPath('event_type', 'meeting.ended');

        $this->assertDatabaseHas('grabaciones', [
            'cita_id' => $cita->id,
            'daily_room_name' => 'room_ended_1',
            'estado' => 'sesion_finalizada',
        ]);

        $this->assertDatabaseHas('citas', [
            'id' => $cita->id,
            'estado' => 'finalizada',
        ]);

        $this->assertNotNull($cita->fresh()->finalizada_en);
    }

    public function test_daily_webhook_meeting_ended_does_not_finalize_already_cancelled_cita(): void
    {
        config(['services.daily.webhook_secret' => 'daily_test_secret']);
        Queue::fake();

        $cliente = User::factory()->create();
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        $cita = Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-DAIL-CANC',
            'cliente_id' => $cliente->id,
            'especialista_id' => null,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => now()->subHour(),
            'fin_utc' => now(),
            'duracion_minutos' => 60,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => 'cancelada_cliente',
            'canal_pago' => 'stripe',
            'precio_total_centavos' => 50000,
            'precio_final_centavos' => 50000,
            'moneda' => 'CLP',
            'es_primera_consulta' => false,
        ]);

        $payload = json_encode([
            'id' => 'evt_daily_ended_canc',
            'type' => 'meeting.ended',
            'data' => [
                'room' => 'room_canc_1',
                'metadata' => [
                    'cita_uuid' => $cita->uuid,
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $payload, 'daily_test_secret');

        $this->call('POST', '/api/webhooks/daily', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_DAILY_SIGNATURE' => $signature,
        ], $payload)->assertOk();

        // Cita debe seguir cancelada — no debe pasar a finalizada
        $this->assertDatabaseHas('citas', [
            'id' => $cita->id,
            'estado' => 'cancelada_cliente',
        ]);
    }

    public function test_daily_webhook_meeting_started_creates_or_updates_session_trace(): void
    {
        config(['services.daily.webhook_secret' => 'daily_test_secret']);
        Queue::fake();

        $cliente = User::factory()->create();
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        $cita = Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-DAIL-MEET',
            'cliente_id' => $cliente->id,
            'especialista_id' => null,
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

        $payload = json_encode([
            'id' => 'evt_daily_meeting_1',
            'type' => 'meeting.started',
            'data' => [
                'room' => 'room_meeting_1',
                'metadata' => [
                    'cita_uuid' => $cita->uuid,
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $payload, 'daily_test_secret');

        $this->call('POST', '/api/webhooks/daily', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_DAILY_SIGNATURE' => $signature,
        ], $payload)
            ->assertOk()
            ->assertJsonPath('received', true)
            ->assertJsonPath('event_type', 'meeting.started');

        $this->assertDatabaseHas('grabaciones', [
            'cita_id' => $cita->id,
            'daily_room_name' => 'room_meeting_1',
            'estado' => 'sesion_iniciada',
        ]);

        Queue::assertNotPushed(ProcesarTranscripcionJob::class);
    }
}
