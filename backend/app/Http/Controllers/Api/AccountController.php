<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consentimiento;
use App\Models\PreferenciaNotificacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function completarPerfil(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre'                  => ['required', 'string', 'max:100'],
            'apellido'                => ['required', 'string', 'max:120'],
            'telefono'                => ['required', 'string', 'max:30'],
            'telefono_pais'           => ['required', 'string', 'max:5'],
            'pais_residencia'         => ['required', 'string', 'max:80'],
            'fecha_nacimiento_publica' => ['required', 'date', 'before:today'],
            'zona_horaria'            => ['nullable', 'string', 'timezone'],
            'genero'                  => ['nullable', 'in:masculino,femenino,no_binario,prefiero_no_decir'],
            'consent_terminos'        => ['nullable', 'boolean'],
            'consent_privacidad'      => ['nullable', 'boolean'],
            'version_documento'       => ['nullable', 'string', 'max:30'],
        ]);

        $user = $request->user();
        $profile = $user->profile()->firstOrCreate(['user_id' => $user->id]);
        $profile->fill($validated);
        $profile->perfil_completado_en = now();
        $profile->save();

        if ($validated['consent_terminos'] ?? false) {
            Consentimiento::query()->updateOrCreate(
                ['user_id' => $user->id, 'tipo' => 'terminos'],
                [
                    'version_documento' => $validated['version_documento'] ?? 'v3.0',
                    'otorgado'          => true,
                    'otorgado_en'       => now(),
                    'ip_otorgamiento'   => $request->ip(),
                    'user_agent'        => substr($request->userAgent() ?? '', 0, 512),
                ]
            );
        }

        if ($validated['consent_privacidad'] ?? false) {
            Consentimiento::query()->updateOrCreate(
                ['user_id' => $user->id, 'tipo' => 'privacidad'],
                [
                    'version_documento' => $validated['version_documento'] ?? 'v3.0',
                    'otorgado'          => true,
                    'otorgado_en'       => now(),
                    'ip_otorgamiento'   => $request->ip(),
                    'user_agent'        => substr($request->userAgent() ?? '', 0, 512),
                ]
            );
        }

        if (($validated['consent_terminos'] ?? false) || ($validated['consent_privacidad'] ?? false)) {
            $clientIp = $request->ip() ?? '—';
            dispatch(function () use ($user, $clientIp) {
                $terminos   = \App\Support\LegalDocs::terminos();
                $privacidad = \App\Support\LegalDocs::privacidad();
                \Illuminate\Support\Facades\Mail::to($user->email)
                    ->send(new \App\Mail\ConsentimientoAceptadoMail(
                        user: $user,
                        ip: $clientIp,
                        version: $terminos['version'] ?? 'v3.0',
                        terminos: $terminos,
                        privacidad: $privacidad,
                    ));
            })->afterResponse();
        }

        return response()->json([
            'message' => 'Perfil completado correctamente.',
            'data' => $profile->fresh(),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre'                  => ['required', 'string', 'max:100'],
            'apellido'                => ['nullable', 'string', 'max:120'],
            'telefono'                => ['nullable', 'string', 'max:30'],
            'zona_horaria'            => ['nullable', 'string', 'timezone'],
            'genero'                  => ['nullable', 'in:masculino,femenino,no_binario,prefiero_no_decir'],
            'fecha_nacimiento_publica' => ['nullable', 'date', 'before:today'],
            'biografia'               => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $profile = $user->profile()->firstOrCreate(['user_id' => $user->id]);
        $profile->update(array_filter($validated, fn ($v) => $v !== null));

        return response()->json([
            'message' => 'Perfil actualizado.',
            'data' => $profile->fresh(),
        ]);
    }

    public function getNotificationPrefs(Request $request): JsonResponse
    {
        $user = $request->user();
        $prefs = $user->preferenciaNotificacion
            ?? PreferenciaNotificacion::create(['user_id' => $user->id]);

        return response()->json(['data' => $prefs]);
    }

    public function updateNotificationPrefs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email_recordatorios'   => ['boolean'],
            'email_marketing'       => ['boolean'],
            'whatsapp_recordatorios' => ['boolean'],
            'whatsapp_marketing'    => ['boolean'],
            'canal_preferido'       => ['nullable', 'string', 'in:email,whatsapp'],
        ]);

        $user = $request->user();
        $prefs = $user->preferenciaNotificacion
            ?? PreferenciaNotificacion::create(['user_id' => $user->id]);

        $prefs->update($validated);

        return response()->json([
            'message' => 'Preferencias actualizadas.',
            'data' => $prefs->fresh(),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'La contraseña actual no es correcta.',
                'errors' => ['current_password' => ['La contraseña actual no es correcta.']],
            ], 422);
        }

        $user->update(['password' => $validated['password']]);

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }
}
