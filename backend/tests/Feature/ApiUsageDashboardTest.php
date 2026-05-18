<?php

namespace Tests\Feature;

use App\Jobs\VerificarConsumoApisJob;
use App\Mail\AlertaConsumoApiMail;
use App\Models\AgenteConversacion;
use App\Models\ApiUsageAlert;
use App\Models\AppSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\ApiUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiUsageDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    private function makeSuperAdmin(): User
    {
        $u = User::factory()->create(['email_verified_at' => now()]);
        $u->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'super_admin')->value('id') => ['asignado_en' => now()],
        ]);
        return $u;
    }

    public function test_endpoint_solo_super_admin(): void
    {
        $u = User::factory()->create();
        $u->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'admin_especialista')->value('id') => ['asignado_en' => now()],
        ]);
        Sanctum::actingAs($u);
        $this->getJson('/api/admin/api-usage')->assertForbidden();

        Sanctum::actingAs($this->makeSuperAdmin());
        $this->getJson('/api/admin/api-usage')->assertOk()
            ->assertJsonStructure(['periodo', 'rows' => [['provider', 'usado', 'limite', 'porcentaje', 'estado']]]);
    }

    public function test_update_limits(): void
    {
        Sanctum::actingAs($this->makeSuperAdmin());
        $this->patchJson('/api/admin/api-usage/limits', [
            'anthropic' => ['limite' => 25, 'warn_pct' => 70],
            'alert_emails' => 'extra@example.com',
        ])->assertOk();

        $svc = app(ApiUsageService::class);
        $limits = $svc->getLimits();
        $this->assertEquals(25.0, $limits['anthropic']['limite']);
        $this->assertEquals(70.0, $limits['anthropic']['warn_pct']);
    }

    public function test_update_limits_allows_clearing_extra_alert_emails(): void
    {
        Sanctum::actingAs($this->makeSuperAdmin());
        AppSetting::query()->updateOrCreate(
            ['key' => ApiUsageService::ALERT_EMAILS],
            ['category' => 'api', 'value' => 'extra@example.com', 'editable_admin' => true]
        );

        $this->patchJson('/api/admin/api-usage/limits', [
            'alert_emails' => '',
        ])->assertOk();

        $this->assertSame('', AppSetting::query()->where('key', ApiUsageService::ALERT_EMAILS)->value('value'));
    }

    public function test_job_dispara_alerta_y_email_cuando_excede(): void
    {
        Mail::fake();
        $svc = app(ApiUsageService::class);

        // Limite muy bajo para forzar exceeded
        AppSetting::query()->updateOrCreate(
            ['key' => ApiUsageService::SETTINGS_KEY],
            ['category' => 'api', 'value' => json_encode(['anthropic' => ['limite' => 0.001, 'warn_pct' => 50]])]
        );

        // Generar uso de Anthropic
        $cliente = User::factory()->create();
        AgenteConversacion::create([
            'uuid' => (string) Str::uuid(),
            'cliente_id' => $cliente->id,
            'autor_user_id' => $cliente->id,
            'autor_rol' => 'cliente',
            'pregunta' => 'x', 'respuesta' => 'y',
            'proveedor' => 'anthropic',
            'modelo' => 'claude-3-5-sonnet-latest',
            'tokens_in' => 1_000_000,
            'tokens_out' => 500_000,
            'latencia_ms' => 100,
        ]);

        // Asegurar al menos un super_admin con email
        $this->makeSuperAdmin();

        (new VerificarConsumoApisJob())->handle($svc);

        $this->assertDatabaseHas('api_usage_alerts', [
            'provider' => 'anthropic',
            'nivel'    => 'exceeded',
        ]);
        Mail::assertSent(AlertaConsumoApiMail::class);

        // Idempotencia: re-correr no duplica
        (new VerificarConsumoApisJob())->handle($svc);
        $this->assertSame(1, ApiUsageAlert::where('provider', 'anthropic')->where('nivel', 'exceeded')->count());
    }

    public function test_alertas_recientes_expose_usado_for_frontend(): void
    {
        Sanctum::actingAs($this->makeSuperAdmin());

        ApiUsageAlert::query()->create([
            'provider' => 'daily',
            'period' => now()->format('Y-m'),
            'nivel' => 'warning',
            'valor_actual' => 81.25,
            'limite' => 100,
            'porcentaje' => 81.25,
            'unidad' => 'minutes',
            'notificado_en' => now(),
        ]);

        $this->getJson('/api/admin/api-usage')
            ->assertOk()
            ->assertJsonPath('alertas_recientes.0.usado', 81.25)
            ->assertJsonPath('alertas_recientes.0.valor_actual', 81.25);
    }
}
