<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Grabacion;
use App\Models\Pago;
use App\Models\Resumen;
use App\Models\Role;
use App\Models\TipoConsulta;
use App\Models\Transcripcion;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminClientesControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_unauthenticated_recibe_401(): void
    {
        $this->getJson('/api/admin/clientes')->assertStatus(401);
    }

    public function test_cliente_no_admin_recibe_403(): void
    {
        $cliente = $this->makeClient();
        Sanctum::actingAs($cliente);
        $this->getJson('/api/admin/clientes')->assertStatus(403);
    }

    public function test_admin_lista_clientes_con_stats(): void
    {
        $admin = $this->makeAdmin();
        $cliente = $this->makeClient(['name' => 'Maria Lopez', 'email' => 'maria@example.com']);
        $this->makeCitaCompletada($cliente, 60, 'CLP', 399000);
        $this->makeCitaCompletada($cliente, 30, 'CLP', 250000);

        Sanctum::actingAs($admin);

        $resp = $this->getJson('/api/admin/clientes')->assertOk();
        $row = collect($resp->json('data'))->firstWhere('email', 'maria@example.com');

        $this->assertNotNull($row);
        $this->assertSame(2, $row['stats']['total_completadas']);
        $this->assertSame(649000, $row['stats']['ingresos_centavos']['CLP']);
    }

    public function test_admin_filtra_por_q(): void
    {
        $admin = $this->makeAdmin();
        $this->makeClient(['name' => 'Pedro Perez', 'email' => 'pedro@x.cl']);
        $this->makeClient(['name' => 'Luis Soto', 'email' => 'luis@x.cl']);

        Sanctum::actingAs($admin);

        $resp = $this->getJson('/api/admin/clientes?q=pedro')->assertOk();
        $emails = collect($resp->json('data'))->pluck('email')->all();
        $this->assertContains('pedro@x.cl', $emails);
        $this->assertNotContains('luis@x.cl', $emails);
    }

    public function test_admin_show_devuelve_perfil_y_resumenes(): void
    {
        $admin = $this->makeAdmin();
        $cliente = $this->makeClient();
        $cita = $this->makeCitaCompletada($cliente, 60, 'CLP', 399000);
        $grab = Grabacion::query()->create([
            'uuid' => (string) Str::uuid(),
            'cita_id' => $cita->id,
            'daily_room_name' => 'r-'.$cita->uuid,
            'estado' => 'transcrita',
        ]);
        $trans = Transcripcion::query()->create([
            'grabacion_id' => $grab->id,
            'cita_id' => $cita->id,
            'contenido' => 'Texto.',
            'idioma' => 'es',
            'proveedor' => 'whisper',
            'modelo' => 'whisper-1',
        ]);
        Resumen::query()->create([
            'cita_id' => $cita->id,
            'transcripcion_id' => $trans->id,
            'contenido' => 'Resumen IA de la sesion.',
            'idioma' => 'es',
            'proveedor' => 'anthropic',
            'modelo' => 'claude-3-5-sonnet-latest',
        ]);

        Sanctum::actingAs($admin);

        $resp = $this->getJson('/api/admin/clientes/'.$cliente->uuid)->assertOk();
        $resp->assertJsonPath('data.uuid', $cliente->uuid)
             ->assertJsonPath('data.stats.total_completadas', 1);
        $this->assertNotEmpty($resp->json('data.resumenes'));
        $this->assertNotEmpty($resp->json('data.citas'));
    }

    public function test_admin_actualiza_notas_privadas(): void
    {
        $admin = $this->makeAdmin();
        $cliente = $this->makeClient();
        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/clientes/'.$cliente->uuid.'/notas', [
            'notas_admin' => 'Cliente abierto, prefiere lectura por la tarde.',
        ])->assertOk()
          ->assertJsonPath('data.notas_admin', 'Cliente abierto, prefiere lectura por la tarde.');

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $cliente->id,
            'notas_admin_actualizadas_por' => $admin->id,
        ]);
    }

    public function test_super_admin_envia_reset_password_a_cliente(): void
    {
        Notification::fake();

        $superAdmin = $this->makeSuperAdmin();
        $cliente = $this->makeClient(['email' => 'cliente-reset@example.com']);

        Sanctum::actingAs($superAdmin);

        $this->postJson('/api/admin/clientes/'.$cliente->uuid.'/reset-password')
            ->assertOk()
            ->assertJsonPath('message', 'Se envio un enlace de restablecimiento al cliente.');

        Notification::assertSentTo($cliente, ResetPasswordNotification::class);
    }

    public function test_admin_no_super_admin_no_puede_enviar_reset_password_a_cliente(): void
    {
        Notification::fake();

        $admin = $this->makeAdmin();
        $cliente = $this->makeClient(['email' => 'cliente-no-reset@example.com']);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/clientes/'.$cliente->uuid.'/reset-password')
            ->assertStatus(403);

        Notification::assertNothingSent();
    }

    public function test_admin_estadisticas_endpoint(): void
    {
        $admin = $this->makeAdmin();
        $cliente = $this->makeClient();
        $this->makeCitaCompletada($cliente, 60, 'CLP', 399000);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/clientes/'.$cliente->uuid.'/estadisticas')
            ->assertOk()
            ->assertJsonPath('data.total_completadas', 1);
    }

    public function test_show_404_si_cliente_no_existe(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/clientes/'.Str::uuid())->assertStatus(404);
    }

    private function makeClient(array $attrs = []): User
    {
        $u = User::factory()->create(array_merge(['email_verified_at' => now()], $attrs));
        $u->profile()->create(['nombre' => $attrs['name'] ?? 'Cliente']);
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

    private function makeSuperAdmin(): User
    {
        $u = User::factory()->create(['email_verified_at' => now()]);
        $u->profile()->create(['nombre' => 'Super Admin']);
        $u->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'super_admin')->value('id') => ['asignado_en' => now()],
        ]);
        return $u;
    }

    private function makeCitaCompletada(User $cliente, int $minutos, string $moneda, int $centavos): Cita
    {
        $tipo = TipoConsulta::query()->where('slug', 'tarot')->firstOrFail();
        $cita = Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-CL-'.Str::upper(Str::random(4)),
            'cliente_id' => $cliente->id,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => now()->subDays(2),
            'fin_utc' => now()->subDays(2)->addMinutes($minutos),
            'duracion_minutos' => $minutos,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => 'completada',
            'canal_pago' => 'stripe',
            'precio_total_centavos' => $centavos,
            'precio_final_centavos' => $centavos,
            'moneda' => $moneda,
            'es_primera_consulta' => false,
        ]);

        Pago::query()->create([
            'uuid' => (string) Str::uuid(),
            'cita_id' => $cita->id,
            'tipo' => 'pago_completo',
            'canal' => 'stripe',
            'monto_centavos' => $centavos,
            'moneda' => $moneda,
            'estado' => 'pagado',
        ]);

        return $cita;
    }
}
