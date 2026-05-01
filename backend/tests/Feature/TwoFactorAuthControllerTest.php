<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_setup_genera_secret(): void
    {
        Sanctum::actingAs($this->makeUser());

        $resp = $this->postJson('/api/me/2fa/setup')->assertOk();
        $this->assertNotEmpty($resp->json('secret'));
        $this->assertStringContainsString('otpauth://', $resp->json('otpauth_uri'));
    }

    public function test_confirm_activa_2fa(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/me/2fa/setup')->assertOk();
        $user->refresh();

        $g2fa = new Google2FA();
        $code = $g2fa->getCurrentOtp($user->two_factor_secret);

        $resp = $this->postJson('/api/me/2fa/confirm', ['code' => $code])->assertOk();
        $this->assertCount(8, $resp->json('recovery_codes'));
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_confirm_falla_con_codigo_invalido(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);
        $this->postJson('/api/me/2fa/setup');

        $this->postJson('/api/me/2fa/confirm', ['code' => '000000'])->assertStatus(422);
    }

    public function test_verify_acepta_recovery_code(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);
        $this->postJson('/api/me/2fa/setup');
        $user->refresh();
        $g2fa = new Google2FA();
        $this->postJson('/api/me/2fa/confirm', ['code' => $g2fa->getCurrentOtp($user->two_factor_secret)])->assertOk();

        $codes = $user->fresh()->two_factor_recovery_codes;

        $resp = $this->postJson('/api/me/2fa/verify', ['code' => $codes[0]])->assertOk();
        $this->assertTrue($resp->json('valid'));
        $this->assertCount(7, $user->fresh()->two_factor_recovery_codes);
    }

    public function test_disable_requiere_password(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);
        $this->postJson('/api/me/2fa/setup');
        $g2fa = new Google2FA();
        $this->postJson('/api/me/2fa/confirm', ['code' => $g2fa->getCurrentOtp($user->fresh()->two_factor_secret)]);

        $this->postJson('/api/me/2fa/disable', ['password' => 'wrong'])->assertStatus(422);
        $this->postJson('/api/me/2fa/disable', ['password' => 'password'])->assertOk();

        $this->assertNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_status_endpoint(): void
    {
        Sanctum::actingAs($this->makeUser());
        $this->getJson('/api/me/2fa/status')->assertOk()->assertJsonPath('enabled', false);
    }

    private function makeUser(): User
    {
        $u = User::factory()->create([
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
        ]);
        $u->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'cliente')->value('id') => ['asignado_en' => now()],
        ]);
        return $u;
    }
}
