<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consentimiento;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'nombre' => ['required', 'string', 'max:120'],
            'apellido' => ['nullable', 'string', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'telefono_pais' => ['nullable', 'string', 'max:8'],
            'pais_residencia' => ['nullable', 'string', 'max:80'],
            'idioma_preferido' => ['nullable', 'string', 'max:8'],
            'zona_horaria' => ['nullable', 'string', 'max:64'],
            'canal_preferido' => ['nullable', 'in:email,whatsapp'],
            'consent_privacidad' => ['required', 'accepted'],
            'consent_terminos' => ['required', 'accepted'],
            'version_documento' => ['nullable', 'string', 'max:30'],
        ]);

        $now = now();
        $versionDocumento = $validated['version_documento'] ?? 'v1';
        $clienteRole = Role::query()->where('nombre', 'cliente')->first();

        if (! $clienteRole) {
            throw ValidationException::withMessages([
                'role' => 'El rol cliente no esta configurado.',
            ]);
        }

        $user = DB::transaction(function () use ($validated, $request, $clienteRole, $now, $versionDocumento) {
            $user = User::query()->create([
                'name' => $validated['nombre'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            $user->profile()->create([
                'nombre' => $validated['nombre'],
                'apellido' => $validated['apellido'] ?? null,
                'telefono' => $validated['telefono'] ?? null,
                'telefono_pais' => $validated['telefono_pais'] ?? null,
                'pais_residencia' => $validated['pais_residencia'] ?? null,
                'idioma_preferido' => $validated['idioma_preferido'] ?? 'es',
            ]);

            $user->preferenciaNotificacion()->create([
                'zona_horaria' => $validated['zona_horaria'] ?? 'America/Santiago',
                'canal_preferido' => $validated['canal_preferido'] ?? 'email',
            ]);

            $user->roles()->syncWithoutDetaching([
                $clienteRole->id => [
                    'asignado_en' => $now,
                    'asignado_por' => null,
                ],
            ]);

            Consentimiento::query()->create([
                'user_id' => $user->id,
                'tipo' => 'privacidad',
                'version_documento' => $versionDocumento,
                'otorgado' => true,
                'otorgado_en' => $now,
                'ip_otorgamiento' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 512),
            ]);

            Consentimiento::query()->create([
                'user_id' => $user->id,
                'tipo' => 'terminos',
                'version_documento' => $versionDocumento,
                'otorgado' => true,
                'otorgado_en' => $now,
                'ip_otorgamiento' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 512),
            ]);

            return $user;
        });

        event(new Registered($user));

        $token = $user->createToken('auth')->plainTextToken;
        $user->load(['profile', 'roles', 'preferenciaNotificacion']);

        return response()->json([
            'message' => 'Registro exitoso.',
            'token' => $token,
            'user' => $user,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        /** @var User|null $user */
        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Debes verificar tu correo electronico para iniciar sesion.',
            ], 403);
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $token = $user->createToken('auth')->plainTextToken;
        $user->load(['profile', 'roles', 'preferenciaNotificacion']);

        return response()->json([
            'message' => 'Inicio de sesion exitoso.',
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink([
            'email' => $validated['email'],
        ]);

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => __($status),
            ]);
        }

        return response()->json([
            'message' => __($status),
        ], 422);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => __($status),
            ]);
        }

        return response()->json([
            'message' => __($status),
        ], 422);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $token = $user->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Sesion cerrada correctamente.',
        ]);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ((int) $request->route('id') !== $user->getKey()) {
            return response()->json([
                'message' => 'No autorizado para verificar este correo.',
            ], 403);
        }

        if (! hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
            return response()->json([
                'message' => 'Firma de verificacion invalida.',
            ], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'El correo ya estaba verificado.',
            ]);
        }

        $user->markEmailAsVerified();

        return response()->json([
            'message' => 'Correo verificado correctamente.',
        ]);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'El correo ya esta verificado.',
            ], 422);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Correo de verificacion reenviado.',
        ]);
    }
}
