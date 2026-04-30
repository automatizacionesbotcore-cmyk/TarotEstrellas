<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\TipoConsulta;
use App\Models\User;
use App\Services\DailyRoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DailyRoomServiceMockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        config()->set('services.daily.api_key', '');
    }

    public function test_is_mock_mode_when_api_key_empty(): void
    {
        $service = new DailyRoomService();
        $this->assertTrue($service->isMockMode());
    }

    public function test_crear_sala_en_mock_persiste_url_y_room_name_en_la_cita(): void
    {
        $cita = $this->makeCita();
        $service = new DailyRoomService();

        $sala = $service->crearSala($cita);

        $this->assertTrue($sala['mock']);
        $this->assertStringStartsWith('https://mock.daily.co/cita-', $sala['room_url']);
        $this->assertStringStartsWith('cita-', $sala['room_name']);

        $cita->refresh();
        $this->assertSame($sala['room_url'], $cita->daily_room_url);
        $this->assertSame($sala['room_name'], $cita->daily_room_name);
    }

    public function test_crear_sala_es_idempotente(): void
    {
        $cita = $this->makeCita();
        $service = new DailyRoomService();

        $a = $service->crearSala($cita);
        $cita->refresh();
        $b = $service->crearSala($cita);

        $this->assertSame($a['room_url'], $b['room_url']);
        $this->assertSame($a['room_name'], $b['room_name']);
    }

    public function test_crear_meeting_token_en_mock_devuelve_jwt_decodificable(): void
    {
        $cita = $this->makeCita();
        $cliente = $cita->cliente;
        $service = new DailyRoomService();
        $service->crearSala($cita);
        $cita->refresh();

        $token = $service->crearMeetingToken($cita, $cliente, isOwner: false);
        $payload = json_decode((string) base64_decode($token), true);

        $this->assertIsArray($payload);
        $this->assertTrue($payload['mock']);
        $this->assertSame($cita->daily_room_name, $payload['room']);
        $this->assertFalse($payload['is_owner']);
    }

    public function test_iniciar_y_detener_grabacion_mock_devuelven_true(): void
    {
        $service = new DailyRoomService();
        $this->assertTrue($service->iniciarGrabacion('cita-x'));
        $this->assertTrue($service->detenerGrabacion('cita-x'));
    }

    private function makeCita(): Cita
    {
        $cliente = User::factory()->create(['email_verified_at' => now()]);
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        return Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-DRSM-'.Str::upper(Str::random(4)),
            'cliente_id' => $cliente->id,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => now()->addMinutes(5),
            'fin_utc' => now()->addMinutes(65),
            'duracion_minutos' => 60,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => 'confirmada',
            'canal_pago' => 'stripe',
            'precio_total_centavos' => 399000,
            'precio_final_centavos' => 399000,
            'moneda' => 'CLP',
            'es_primera_consulta' => false,
            'grabacion_solicitada' => true,
        ]);
    }
}
