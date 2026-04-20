<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Role;
use App\Models\TipoConsulta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CitaCalendarioIcsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_client_can_download_own_appointment_as_ics(): void
    {
        $cliente = $this->makeAuthenticatedClient();
        Sanctum::actingAs($cliente);

        $cita = $this->createCitaForClient($cliente, 'TE-ICS-OWN1');

        $response = $this->get('/api/citas/'.$cita->uuid.'/calendario-ics');

        $dtStart = $cita->inicio_utc->copy()->utc()->format('Ymd\\THis\\Z');
        $dtEnd = $cita->fin_utc->copy()->utc()->format('Ymd\\THis\\Z');

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/calendar; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename="cita-'.$cita->uuid.'.ics"')
            ->assertSee('BEGIN:VCALENDAR', false)
            ->assertSee('BEGIN:VEVENT', false)
            ->assertSee('UID:'.$cita->uuid.'@tarotestrellas.com', false)
            ->assertSee('DTSTART:'.$dtStart, false)
            ->assertSee('DTEND:'.$dtEnd, false)
            ->assertSee('SUMMARY:Consulta Tarot Estrellas - Lectura de Tarot', false)
            ->assertSee('END:VCALENDAR', false);
    }

    public function test_client_cannot_download_ics_for_another_clients_appointment(): void
    {
        $clienteA = $this->makeAuthenticatedClient();
        $clienteB = $this->makeAuthenticatedClient();

        $citaDeB = $this->createCitaForClient($clienteB, 'TE-ICS-OTR1');

        Sanctum::actingAs($clienteA);

        $this->get('/api/citas/'.$citaDeB->uuid.'/calendario-ics')
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
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'codigo_referencia' => $codigoReferencia,
            'cliente_id' => $cliente->id,
            'especialista_id' => null,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => now()->addDay()->setTime(14, 0, 0),
            'fin_utc' => now()->addDay()->setTime(16, 0, 0),
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
