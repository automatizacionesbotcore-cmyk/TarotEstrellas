<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Cita;
use App\Models\Role;
use App\Models\TipoConsulta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_observer_persists_created_and_updated_for_cita(): void
    {
        $cliente = User::factory()->create();
        $tipo = TipoConsulta::factory()->create();

        $cita = Cita::create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => 'TE-A-' . Str::upper(Str::random(4)),
            'cliente_id' => $cliente->id,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => now()->addHour(),
            'fin_utc' => now()->addHour()->addMinutes(30),
            'duracion_minutos' => 30,
            'zona_horaria_cliente' => 'America/Santiago',
            'estado' => 'reservada',
            'canal_pago' => 'stripe',
            'precio_total_centavos' => 250000,
            'precio_final_centavos' => 250000,
            'moneda' => 'CLP',
            'es_primera_consulta' => false,
        ]);

        $cita->update(['estado' => 'confirmada']);

        $logs = AuditLog::where('auditable_type', Cita::class)->where('auditable_id', $cita->id)->get();
        $this->assertGreaterThanOrEqual(2, $logs->count());

        $created = $logs->firstWhere('action', 'created');
        $updated = $logs->firstWhere('action', 'updated');
        $this->assertNotNull($created);
        $this->assertNotNull($updated);
        $this->assertSame('reservada', $updated->changes['old']['estado'] ?? null);
        $this->assertSame('confirmada', $updated->changes['new']['estado'] ?? null);
    }

    public function test_admin_audit_logs_endpoint(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'super_admin')->value('id') => ['asignado_en' => now()],
        ]);
        Sanctum::actingAs($admin);

        AuditLog::create([
            'uuid'   => (string) Str::uuid(),
            'action' => 'created',
            'auditable_type' => 'App\\Models\\Cita',
            'auditable_id'   => 1,
        ]);

        $this->getJson('/api/admin/audit-logs?action=created')
            ->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'total']);
    }

    public function test_admin_audit_logs_forbidden_for_non_super_admin(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'admin_especialista')->value('id') => ['asignado_en' => now()],
        ]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/audit-logs')->assertForbidden();
    }

    public function test_login_creates_audit_log(): void
    {
        $user = User::factory()->create([
            'email' => 'audit@test.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        $this->postJson('/api/auth/login', [
            'email'    => 'audit@test.com',
            'password' => 'password',
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action'     => 'login',
            'user_email' => 'audit@test.com',
        ]);
    }

    public function test_failed_login_creates_audit_log(): void
    {
        $this->postJson('/api/auth/login', [
            'email'    => 'nobody@test.com',
            'password' => 'wrong',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('audit_logs', [
            'action'     => 'login_failed',
            'user_email' => 'nobody@test.com',
        ]);
    }
}
