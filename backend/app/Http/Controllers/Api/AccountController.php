<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PreferenciaNotificacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
        ]);

        $user = $request->user();
        $profile = $user->profile()->firstOrCreate(['user_id' => $user->id]);
        $profile->update(['nombre' => $validated['nombre']]);

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
