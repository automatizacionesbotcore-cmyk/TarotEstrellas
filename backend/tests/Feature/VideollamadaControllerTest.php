<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\TipoConsulta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VideollamadaControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        config()->set('services.daily.api_key', '');
    }

    public function test_unauthenticated_user_cannot_enter_room(): void
    {
        $this->getJson('/api/me/citas/'.Str::uuid().'/sala-video')->assertStatus(401);
    }

    public function test_cliente_dentro_de_ventana_recibe_url_y_token(): void
    {
        $cliente = User::factory()->create(['email_verified_at' => now()]);
        $cita = $this->makeCita($cliente, inicio: now()->addMinutes(5), fin: now()->addMinutes(65));

        Sanctum::actingAs($cliente);

        $response = $this->getJson('/api/me/citas/'.$cita->uuid.'/sala-video')
            ->assertOk()
            ->assertJsonPath('data.cita_uuid', $cita->uuid)
            ->assertJsonPath('data.is_owner', false)
            ->assertJsonPath('data.mock', true)
            ->assertJsonStructure(['data' => ['room_url', 'room_name', 'token', 'expira_en']]);

        $this->assertStringStartsWith('https://mock.daily.co/cita-', $response->json('data.room_url'));
    }

    public function test_cliente_antes_de_la_ventana_recibe_409(): void
    {
        $cliente = User::factory()->create(['email_verified_at' => now()]);
        $cita = $this->makeCita($cliente, inicio: now()->addHours(2), fin: now()->addHours(3));

        Sanctum::actingAs($cliente);

        $this->getJson('/api/me/citas/'.$cita->uuid.'/sala-video')
            ->assertStatus(409)
            ->assertJsonStructure(['message', 'abre_en']);
    }

    public function test_cliente_despues_de_la_ventana_recibe_410(): void
    {
        $cliente = User::factory()->create(['email_verified_at' => now()]);
        $cita = $this->makeCita($cliente, inicio: now()->subHours(3), fin: now()->subHours(2));

        Sanctum::actingAs($cliente);

        $this->getJson('/api/me/citas/'.$cita->uuid.'/sala-video')
            ->assertStatus(410);
    }

    public function test_otro_cliente_no_autorizado_recibe_403(): void
    {
        $cliente = User::factory()->create(['email_verified_at' => now()]);
        $intruso = User::factory()->create(['email_verified_at' => now()]);
        $cita = $this->makeCita($cliente, inicio: now()->addMinutes(5), fin: now()->addMinutes(65));

        Sanctum::actingAs($intruso);

        $this->getJson('/api/me/citas/'.$cita->uuid.'/sala-video')->assertStatus(403);
    }

    public function test_iniciar_grabacion_requiere_grabacion_solicitada(): void
    {
        $cliente = User::factory()->create(['email_verified_at' => now()]);
        $especialista = User::factory()->create(['email_verified_at' => now()]);
        $cita = $this->makeCita($cliente, inicio: now()->addMinutes(5), fin: now()->addMinutes(65), especialista: $especialista, grabacion: false);
        $cita->forceFill(['daily_room_name' => 'cita-test'])->save();

        Sanctum::actingAs($especialista);

        $this->postJson('/api/me/citas/'.$cita->uuid.'/sala-video/grabacion/iniciar')
            ->assertStatus(422);
    }

    public function test_iniciar_grabacion_solo_owner_o_admin(): void
    {
        $cliente = User::factory()->create(['email_verified_at' => now()]);
        $especialista = User::factory()->create(['email_verified_at' => now()]);
        $cita = $this->makeCita($cliente, inicio: now()->addMinutes(5), fin: now()->addMinutes(65), especialista: $especialista, grabacion: true);
        $cita->forceFill(['daily_room_name' => 'cita-test'])->save();

        Sanctum::actingAs($cliente);
        $this->postJson('/api/me/citas/'.$cita->uuid.'/sala-video/grabacion/iniciar')
            ->assertStatus(403);

        Sanctum::actingAs($especialista);
        $this->postJson('/api/me/citas/'.$cita->uuid.'/sala-video/grabacion/iniciar')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->postJson('/api/me/citas/'.$cita->uuid.'/sala-video/grabacion/detener')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    private function makeCita(User $cliente, $inicio, $fin, ?User $especialista = null, bool $grabacion = true): Cita
    {
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        return Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-VIDEO-'.Str::upper(Str::random(4)),
            'cliente_id' => $cliente->id,
            'especialista_id' => $especialista?->id,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => $inicio,
            'fin_utc' => $fin,
            'duracion_minutos' => 60,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => 'confirmada',
            'canal_pago' => 'stripe',
            'precio_total_centavos' => 399000,
            'precio_final_centavos' => 399000,
            'moneda' => 'CLP',
            'es_primera_consulta' => false,
            'grabacion_solicitada' => $grabacion,
        ]);
    }
}
