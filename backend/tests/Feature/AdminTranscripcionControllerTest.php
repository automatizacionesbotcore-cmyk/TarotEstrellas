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

/**
 * Matriz de permisos de transcripción:
 *
 * | Rol              | Ver cruda         | Descargar TXT | Ver resumen IA |
 * |------------------|-------------------|---------------|----------------|
 * | cliente          | ❌                | ❌            | ✅ solo el suyo|
 * | admin_especialista| ✅ solo sus citas | ❌            | ✅             |
 * | super_admin      | ✅ todas          | ✅            | ✅             |
 */
class AdminTranscripcionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    public function test_unauthenticated_cannot_access(): void
    {
        $uuid = (string) Str::uuid();
        $this->getJson('/api/admin/citas/'.$uuid.'/transcripcion')->assertStatus(401);
        $this->getJson('/api/admin/citas/'.$uuid.'/transcripcion/descargar')->assertStatus(401);
    }

    // ─────────────────────────────────────────────
    // Cliente: solo puede ver su resumen (via /me)
    // ─────────────────────────────────────────────

    public function test_cliente_no_puede_ver_transcripcion_cruda(): void
    {
        $cliente = $this->makeClient();
        $cita    = $this->makeCitaConTranscripcion($cliente);

        Sanctum::actingAs($cliente);

        $this->getJson('/api/admin/citas/'.$cita->uuid.'/transcripcion')->assertForbidden();
        $this->getJson('/api/admin/citas/'.$cita->uuid.'/transcripcion/descargar')->assertForbidden();
    }

    public function test_cliente_puede_ver_su_propio_resumen(): void
    {
        $cliente = $this->makeClient();
        $cita    = $this->makeCitaConTranscripcion($cliente);

        Sanctum::actingAs($cliente);

        // El endpoint /me/consultas/{uuid}/resumen debe funcionar para el cliente.
        $this->getJson('/api/me/consultas/'.$cita->uuid.'/resumen')
            ->assertOk()
            ->assertJsonPath('data.cita_uuid', $cita->uuid);
    }

    /** El endpoint /me/consultas/{uuid}/transcripcion siempre devuelve 403 al cliente. */
    public function test_cliente_recibe_403_al_pedir_transcripcion_propia(): void
    {
        $cliente = $this->makeClient();
        $cita    = $this->makeCitaConTranscripcion($cliente);

        Sanctum::actingAs($cliente);

        $this->getJson('/api/me/consultas/'.$cita->uuid.'/transcripcion')->assertForbidden();
    }

    // ─────────────────────────────────────────────
    // admin_especialista: ve cruda solo de SUS citas
    // ─────────────────────────────────────────────

    public function test_especialista_ve_transcripcion_de_su_propia_cita(): void
    {
        $cliente      = $this->makeClient();
        $especialista = $this->makeEspecialista();
        $cita         = $this->makeCitaConTranscripcion($cliente, $especialista);

        Sanctum::actingAs($especialista);

        $this->getJson('/api/admin/citas/'.$cita->uuid.'/transcripcion')
            ->assertOk()
            ->assertJsonPath('data.cita_uuid', $cita->uuid)
            ->assertJsonPath('data.transcripcion.contenido', 'Texto crudo de la sesion.');
    }

    public function test_especialista_no_puede_ver_transcripcion_de_cita_ajena(): void
    {
        $cliente          = $this->makeClient();
        $otroEspecialista = $this->makeEspecialista();
        $cita             = $this->makeCitaConTranscripcion($cliente); // sin especialista asignado

        Sanctum::actingAs($otroEspecialista);

        $this->getJson('/api/admin/citas/'.$cita->uuid.'/transcripcion')->assertForbidden();
    }

    public function test_especialista_no_puede_descargar_transcripcion(): void
    {
        $cliente      = $this->makeClient();
        $especialista = $this->makeEspecialista();
        $cita         = $this->makeCitaConTranscripcion($cliente, $especialista);

        Sanctum::actingAs($especialista);

        $this->getJson('/api/admin/citas/'.$cita->uuid.'/transcripcion/descargar')->assertForbidden();
    }

    // ─────────────────────────────────────────────
    // super_admin: ve y descarga CUALQUIER cita
    // ─────────────────────────────────────────────

    public function test_super_admin_ve_cualquier_transcripcion(): void
    {
        $cliente    = $this->makeClient();
        $superAdmin = $this->makeSuperAdmin();
        $cita       = $this->makeCitaConTranscripcion($cliente); // sin especialista

        Sanctum::actingAs($superAdmin);

        $this->getJson('/api/admin/citas/'.$cita->uuid.'/transcripcion')
            ->assertOk()
            ->assertJsonPath('data.cita_uuid', $cita->uuid)
            ->assertJsonPath('data.transcripcion.contenido', 'Texto crudo de la sesion.');
    }

    public function test_super_admin_descarga_transcripcion_como_txt(): void
    {
        $cliente    = $this->makeClient();
        $superAdmin = $this->makeSuperAdmin();
        $cita       = $this->makeCitaConTranscripcion($cliente);

        Sanctum::actingAs($superAdmin);

        $response = $this->get('/api/admin/citas/'.$cita->uuid.'/transcripcion/descargar');
        $response->assertOk();
        $response->assertHeader('content-type', 'text/plain; charset=UTF-8');
        $this->assertStringContainsString('Texto crudo de la sesion.', $response->streamedContent());
        $this->assertStringContainsString($cita->codigo_referencia, $response->streamedContent());
    }

    // ─────────────────────────────────────────────
    // Helpers privados
    // ─────────────────────────────────────────────

    private function makeClient(): User
    {
        $u = User::factory()->create(['email_verified_at' => now()]);
        $u->profile()->create(['nombre' => 'Cliente Test']);
        $u->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'cliente')->value('id') => ['asignado_en' => now()],
        ]);
        return $u;
    }

    private function makeEspecialista(): User
    {
        $u = User::factory()->create(['email_verified_at' => now()]);
        $u->profile()->create(['nombre' => 'Especialista Test']);
        $u->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'admin_especialista')->value('id') => ['asignado_en' => now()],
        ]);
        return $u;
    }

    private function makeSuperAdmin(): User
    {
        $u = User::factory()->create(['email_verified_at' => now()]);
        $u->profile()->create(['nombre' => 'Super Admin Test']);
        $u->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'super_admin')->value('id') => ['asignado_en' => now()],
        ]);
        return $u;
    }

    private function makeCitaConTranscripcion(User $cliente, ?User $especialista = null): Cita
    {
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();

        $cita = Cita::query()->create([
            'uuid'                   => (string) Str::uuid(),
            'codigo_referencia'      => 'TE-ADM-'.Str::upper(Str::random(4)),
            'cliente_id'             => $cliente->id,
            'especialista_id'        => $especialista?->id,
            'tipo_consulta_id'       => $tipo->id,
            'inicio_utc'             => now()->subDay(),
            'fin_utc'                => now()->subDay()->addMinutes(60),
            'duracion_minutos'       => 60,
            'zona_horaria_cliente'   => 'America/Santiago',
            'estado'                 => 'resumen_completado',
            'canal_pago'             => 'stripe',
            'precio_total_centavos'  => 399000,
            'precio_final_centavos'  => 399000,
            'moneda'                 => 'CLP',
            'es_primera_consulta'    => false,
        ]);

        $grabacion = Grabacion::query()->create([
            'uuid'               => (string) Str::uuid(),
            'cita_id'            => $cita->id,
            'daily_room_name'    => 'cita-'.$cita->uuid,
            'daily_recording_id' => 'rec_'.Str::random(8),
            'url_grabacion'      => 'https://mock.daily.co/rec/x.mp4',
            'estado'             => 'transcrita',
        ]);

        $transcripcion = Transcripcion::query()->create([
            'grabacion_id' => $grabacion->id,
            'cita_id'      => $cita->id,
            'contenido'    => 'Texto crudo de la sesion.',
            'idioma'       => 'es',
            'proveedor'    => 'whisper',
            'modelo'       => 'whisper-1',
        ]);

        // Crear resumen para poder testear /me/consultas/{uuid}/resumen.
        \App\Models\Resumen::query()->create([
            'cita_id'          => $cita->id,
            'grabacion_id'     => $grabacion->id,
            'transcripcion_id' => $transcripcion->id,
            'contenido'        => 'Resumen generado por IA.',
        ]);

        return $cita;
    }
}
