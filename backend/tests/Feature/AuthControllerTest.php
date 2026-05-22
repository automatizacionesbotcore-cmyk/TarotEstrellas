<?php

namespace Tests\Feature;

use App\Models\Consentimiento;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
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

        $forgotResponse
            ->assertOk()
            ->assertJsonPath('message', 'Te enviamos un enlace para restablecer tu contraseña.');

        $token = Password::broker()->createToken($user);

        $resetResponse = $this->postJson('/api/auth/reset-password', [
            'email' => 'reset@example.com',
            'token' => $token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $resetResponse
            ->assertOk()
            ->assertJsonPath('message', 'Tu contraseña fue restablecida correctamente.');

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
    }

    public function test_reset_password_verifica_correo_y_permite_login(): void
    {
        $user = User::factory()->create([
            'email' => 'reset-unverified@example.com',
            'email_verified_at' => null,
            'password' => Hash::make('OldPassword123!'),
        ]);

        $token = Password::broker()->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'reset-unverified@example.com',
            'token' => $token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk();

        $this->assertNotNull($user->fresh()->email_verified_at);

        $this->postJson('/api/auth/login', [
            'email' => 'reset-unverified@example.com',
            'password' => 'NewPassword123!',
        ])->assertOk()
          ->assertJsonStructure(['token', 'user']);
    }

    public function test_resend_verification_sends_notification_for_unverified_user(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/resend-verification')
            ->assertOk()
            ->assertJsonPath('message', 'Correo de verificacion reenviado.');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_email_verification_signed_link_does_not_require_authentication(): void
    {
        $user = User::factory()->create([
            'email' => 'verify-public@example.com',
            'email_verified_at' => null,
        ]);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('message', 'Correo verificado correctamente.');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_resend_verification_returns_422_for_verified_user(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/resend-verification')
            ->assertStatus(422)
            ->assertJsonPath('message', 'El correo ya esta verificado.');

        Notification::assertNothingSent();
    }

    public function test_resend_verification_requires_authentication(): void
    {
        $this->postJson('/api/auth/resend-verification')
            ->assertStatus(401);
    }
}
