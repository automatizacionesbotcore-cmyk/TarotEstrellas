<?php

namespace Tests\Feature;

use App\Models\Consentimiento;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_register_creates_user_profile_role_preferences_and_consentimientos(): void
    {
        $payload = [
            'email' => 'cliente@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'nombre' => 'Ana',
            'apellido' => 'Rojas',
            'consent_privacidad' => true,
            'consent_terminos' => true,
            'version_documento' => 'v2',
        ];

        $response = $this->postJson('/api/auth/register', $payload);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'message',
                'token',
                'user' => ['id', 'email'],
            ]);

        $user = User::query()->where('email', 'cliente@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->roles()->where('nombre', 'cliente')->exists());
        $this->assertNotNull($user->profile);
        $this->assertNotNull($user->preferenciaNotificacion);

        $this->assertEquals(
            2,
            Consentimiento::query()->where('user_id', $user->id)->count()
        );
    }

    public function test_register_requires_legal_consent_fields(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'noconsent@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'nombre' => 'Sin Consentimiento',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['consent_privacidad', 'consent_terminos']);
    }

    public function test_login_returns_token_for_verified_user(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('Password123!'),
            'email_verified_at' => now(),
        ]);

        $user->profile()->create([
            'nombre' => 'Usuario',
        ]);

        $user->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', 'cliente')->value('id') => ['asignado_en' => now()],
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'Password123!',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email']]);

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_forgot_and_reset_password_flow(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'email_verified_at' => now(),
        ]);

        $forgotResponse = $this->postJson('/api/auth/forgot-password', [
            'email' => 'reset@example.com',
        ]);

        $forgotResponse->assertOk();

        $token = Password::broker()->createToken($user);

        $resetResponse = $this->postJson('/api/auth/reset-password', [
            'email' => 'reset@example.com',
            'token' => $token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $resetResponse->assertOk();

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
    }
}
