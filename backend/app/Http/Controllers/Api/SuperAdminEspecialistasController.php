<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PerfilEspecialista;
use App\Models\Role;
use App\Models\User;
use App\Support\PasswordResetMessages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class SuperAdminEspecialistasController extends Controller
{
    public function index(): JsonResponse
    {
        $especialistas = User::query()
            ->whereHas('roles', fn ($q) => $q->where('nombre', 'admin_especialista'))
            ->with([
                'profile:user_id,nombre,apellido,biografia,avatar_url,telefono',
                'perfilEspecialista',
            ])
            ->orderBy('id')
            ->get()
            ->map(fn ($u) => $this->formatEspecialista($u));

        return response()->json(['data' => $especialistas]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre'       => ['required', 'string', 'max:120'],
            'apellido'     => ['nullable', 'string', 'max:120'],
            'email'        => ['required', 'email', 'unique:users,email'],
            'especialidad' => ['nullable', 'string', 'max:200'],
            'slug'         => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', 'unique:perfil_especialista,slug'],
            'orden'        => ['nullable', 'integer', 'min:0'],
        ]);

        $role = Role::query()->where('nombre', 'admin_especialista')->firstOrFail();

        $user = DB::transaction(function () use ($validated, $role) {
            $user = User::query()->create([
                'name'              => $validated['nombre'],
                'email'             => $validated['email'],
                'password'          => Hash::make(Str::random(32)),
                'email_verified_at' => now(),
            ]);

            $user->profile()->create([
                'nombre'   => $validated['nombre'],
                'apellido' => $validated['apellido'] ?? null,
            ]);

            $user->preferenciaNotificacion()->create([
                'zona_horaria'    => 'America/Santiago',
                'canal_preferido' => 'email',
            ]);

            $user->roles()->syncWithoutDetaching([
                $role->id => ['asignado_en' => now(), 'asignado_por' => null],
            ]);

            PerfilEspecialista::query()->create([
                'user_id'      => $user->id,
                'slug'         => $validated['slug'],
                'especialidad' => $validated['especialidad'] ?? null,
                'activo'       => true,
                'orden_display' => $validated['orden'] ?? 0,
            ]);

            return $user;
        });

        // Enviar correo de reset de contraseña para que el especialista configure la suya
        Password::sendResetLink(['email' => $user->email]);

        $user->load(['profile', 'perfilEspecialista']);

        return response()->json(['data' => $this->formatEspecialista($user)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::query()
            ->whereHas('roles', fn ($q) => $q->where('nombre', 'admin_especialista'))
            ->findOrFail($id);

        $validated = $request->validate([
            'nombre'       => ['sometimes', 'string', 'max:120'],
            'apellido'     => ['nullable', 'string', 'max:120'],
            'especialidad' => ['nullable', 'string', 'max:200'],
            'biografia'    => ['nullable', 'string', 'max:1000'],
            'slug'         => ['sometimes', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', 'unique:perfil_especialista,slug,'.$user->perfilEspecialista?->id],
            'orden'        => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($user, $validated) {
            $profileData = array_filter([
                'nombre'   => $validated['nombre'] ?? null,
                'apellido' => $validated['apellido'] ?? null,
                'biografia' => $validated['biografia'] ?? null,
            ], fn ($v) => $v !== null);

            if ($profileData) {
                $user->profile()->updateOrCreate(['user_id' => $user->id], $profileData);
            }

            $perfilData = array_filter([
                'slug'         => $validated['slug'] ?? null,
                'especialidad' => $validated['especialidad'] ?? null,
                'orden_display' => isset($validated['orden']) ? (int) $validated['orden'] : null,
            ], fn ($v) => $v !== null);

            if ($perfilData) {
                $user->perfilEspecialista()->updateOrCreate(['user_id' => $user->id], $perfilData);
            }
        });

        $user->load(['profile', 'perfilEspecialista']);

        return response()->json(['data' => $this->formatEspecialista($user)]);
    }

    public function toggle(int $id): JsonResponse
    {
        $user = User::query()
            ->whereHas('roles', fn ($q) => $q->where('nombre', 'admin_especialista'))
            ->findOrFail($id);

        $perfil = $user->perfilEspecialista;

        if (! $perfil) {
            return response()->json(['message' => 'Este usuario no tiene perfil de especialista.'], 422);
        }

        $perfil->update(['activo' => ! $perfil->activo]);

        return response()->json(['data' => ['activo' => $perfil->fresh()->activo]]);
    }

    public function resetPassword(int $id): JsonResponse
    {
        $user = User::query()
            ->whereHas('roles', fn ($q) => $q->where('nombre', 'admin_especialista'))
            ->findOrFail($id);

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            return response()->json(['message' => PasswordResetMessages::get($status)], 422);
        }

        return response()->json([
            'message' => 'Se envio un enlace de restablecimiento al especialista.',
        ]);
    }

    private function formatEspecialista(User $u): array
    {
        return [
            'id'           => $u->id,
            'uuid'         => $u->uuid,
            'email'        => $u->email,
            'nombre'       => $u->profile?->nombre ?? $u->name,
            'apellido'     => $u->profile?->apellido,
            'telefono'     => $u->profile?->telefono,
            'biografia'    => $u->profile?->biografia,
            'avatar_url'   => $u->profile?->avatar_url,
            'slug'         => $u->perfilEspecialista?->slug,
            'especialidad' => $u->perfilEspecialista?->especialidad,
            'activo'       => $u->perfilEspecialista?->activo ?? false,
            'orden'        => $u->perfilEspecialista?->orden_display ?? 0,
            'tiene_perfil' => $u->perfilEspecialista !== null,
        ];
    }
}
