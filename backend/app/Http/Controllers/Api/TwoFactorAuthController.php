<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthController extends Controller
{
    /**
     * Inicia el flujo: genera secret y devuelve QR PNG en base64.
     */
    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->two_factor_confirmed_at) {
            return response()->json(['message' => 'Ya tienes 2FA habilitado.'], 422);
        }

        $g2fa = $this->google2fa();
        $secret = $g2fa->generateSecretKey(32);
        $user->forceFill(['two_factor_secret' => $secret])->save();

        $appName = config('app.name', 'TarotEstrella');
        $otpauth = $g2fa->getQRCodeUrl($appName, $user->email, $secret);

        $qrPng = null;
        try {
            $renderer = new GDLibRenderer(220);
            $writer = new Writer($renderer);
            $qrPng = base64_encode($writer->writeString($otpauth));
        } catch (\Throwable $e) {
            // Fallback: solo devolvemos el otpauth URI; el cliente puede pegarlo manualmente
        }

        return response()->json([
            'secret'      => $secret,
            'otpauth_uri' => $otpauth,
            'qr_png_b64'  => $qrPng,
        ]);
    }

    /**
     * Confirma activacion verificando un OTP.
     */
    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $user = $request->user();

        if (! $user->two_factor_secret) {
            return response()->json(['message' => 'Inicia el setup primero.'], 422);
        }

        if (! $this->google2fa()->verifyKey($user->two_factor_secret, $data['code'])) {
            return response()->json(['message' => 'Codigo invalido.'], 422);
        }

        $codes = $this->generateRecoveryCodes();
        $user->forceFill([
            'two_factor_confirmed_at'   => now(),
            'two_factor_recovery_codes' => $codes,
        ])->save();

        return response()->json([
            'message'        => '2FA activado.',
            'recovery_codes' => $codes,
        ]);
    }

    /**
     * Verifica un OTP de un usuario ya autenticado (re-autenticacion para acciones sensibles).
     */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'min:6', 'max:32']]);
        $user = $request->user();

        if (! $user->two_factor_confirmed_at || ! $user->two_factor_secret) {
            return response()->json(['message' => '2FA no esta habilitado.'], 422);
        }

        $code = trim($data['code']);

        if (preg_match('/^\d{6}$/', $code)) {
            if ($this->google2fa()->verifyKey($user->two_factor_secret, $code)) {
                return response()->json(['valid' => true]);
            }
        }

        $codes = $user->two_factor_recovery_codes ?? [];
        if (in_array($code, $codes, true)) {
            $codes = array_values(array_diff($codes, [$code]));
            $user->forceFill(['two_factor_recovery_codes' => $codes])->save();
            return response()->json(['valid' => true, 'recovery_used' => true, 'remaining' => count($codes)]);
        }

        return response()->json(['message' => 'Codigo invalido.'], 422);
    }

    private function google2fa(): Google2FA
    {
        return new Google2FA();
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->two_factor_confirmed_at) {
            return response()->json(['message' => '2FA no esta habilitado.'], 422);
        }

        $codes = $this->generateRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();

        return response()->json(['recovery_codes' => $codes]);
    }

    public function disable(Request $request): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();

        if (! \Illuminate\Support\Facades\Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Password incorrecto.'], 422);
        }

        $user->forceFill([
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at'   => null,
        ])->save();

        return response()->json(['message' => '2FA desactivado.']);
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'enabled'           => (bool) $user->two_factor_confirmed_at,
            'confirmed_at'      => $user->two_factor_confirmed_at?->toIso8601String(),
            'recovery_remaining' => $user->two_factor_recovery_codes ? count($user->two_factor_recovery_codes) : 0,
        ]);
    }

    private function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = Str::lower(Str::random(5)) . '-' . Str::lower(Str::random(5));
        }
        return $codes;
    }
}
