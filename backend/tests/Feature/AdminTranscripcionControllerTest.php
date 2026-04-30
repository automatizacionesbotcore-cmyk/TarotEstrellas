<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Grabacion;
use App\Models\Role;
use App\Models\TipoConsulta;
use App\Models\Transcripcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTranscripcionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_unauthenticated_cannot_access(): void
    {
        $uuid = (string) Str::uuid();
        $this->getJson('/api/admin/citas/'.$uuid.'/transcripcion')->assertStatus(401);
        $this->getJson('/api/admin/citas/'.$uuid.'/transcripcion/descargar')->assertStatus(401);
    }

    public function test_cliente_no_puede_ver_transcripcion_admin(): void
    {
        $cliente = $this->makeClient();
        $cita = $this->makeCitaConTranscripcion($cliente);

        Sanctum::actingAs($cliente);

        $this->getJson('/api/admin/citas/'.$cita->uuid.'/transcripcion')
            ->assertStatus(403);
        $this->getJson('/api/admin/citas/'.$cita->uuid.'/transcripcion/descargar')
            ->assertStatus(403);
    }

    public function test_admin_ve_transcripcion_y_la_descarga_como_txt(): void
    {
        $cliente = $this->makeClient();
        $admin = $this->makeAdmin();
        $cita = $this->makeCitaConTranscripcion($cliente);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/citas/'.$cita->uuid.'/transcripcion')
            ->assertOk()
            ->assertJsonPath('data.cita_uuid', $cita->uuid)
            ->assertJsonPath('data.transcripcion.contenido', 'Texto crudo de la sesion.');

        $response = $this->get('/api/admin/citas/'.$cita->uuid.'/transcripcion/descargar');
        $response->assertOk();
        $response->assertHeader('content-type', 'text/plain; charset=UTF-8');
        $this->assertStringContainsString('Texto crudo de la sesion.', $response->streamedContent());
        $this->assertStringContainsString($cita->codigo_referencia, $response->streamedContent());
    }

    public function test_cliente_recibe_403_al_pedir_transcripcion_propia(): void
    {
        $cliente = $this->makeClient();
        $cita = $this->makeCitaConTranscripcion($cliente);

        Sanctum::actingAs($cliente);

        $this->getJson('/api/me/consultas/'.$cita->uuid.'/transcripcion')
            ->assertStatus(403);
    }

    private function makeClient(): User
    {
        $u = User::factory()->create(['email_verified_at' => now()]);
        $u->profile()->create(['nombre' => 'Cliente']);
        $u->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'cliente')->value('id') => ['asignado_en' => now()],
        ]);
        return $u;
    }

    private function makeAdmin(): User
    {
        $u = User::factory()->create(['email_verified_at' => now()]);
        $u->profile()->create(['nombre' => 'Admin']);
        $u->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'admin_especialista')->value('id') => ['asignado_en' => now()],
        ]);
        return $u;
    }

    private function makeCitaConTranscripcion(User $cliente): Cita
    {
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        $cita = Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-ADM-'.Str::upper(Str::random(4)),
            'cliente_id' => $cliente->id,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => now()->subDay(),
            'fin_utc' => now()->subDay()->addMinutes(60),
            'duracion_minutos' => 60,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => 'resumen_completado',
            'canal_pago' => 'stripe',
            'precio_total_centavos' => 399000,
            'precio_final_centavos' => 399000,
            'moneda' => 'CLP',
            'es_primera_consulta' => false,
        ]);

        $grabacion = Grabacion::query()->create([
            'uuid' => (string) Str::uuid(),
            'cita_id' => $cita->id,
            'daily_room_name' => 'cita-'.$cita->uuid,
            'daily_recording_id' => 'rec_'.Str::random(8),
            'url_grabacion' => 'https://mock.daily.co/rec/x.mp4',
            'estado' => 'transcrita',
        ]);

        Transcripcion::query()->create([
            'grabacion_id' => $grabacion->id,
            'cita_id' => $cita->id,
            'contenido' => 'Texto crudo de la sesion.',
            'idioma' => 'es',
            'proveedor' => 'whisper',
            'modelo' => 'whisper-1',
        ]);

        return $cita;
    }
}
