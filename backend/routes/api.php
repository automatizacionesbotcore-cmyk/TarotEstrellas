<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminTransferValidationSettingController;
use App\Http\Controllers\Api\CitaController;
use App\Http\Controllers\Api\ComprobanteTransferenciaController;
use App\Http\Controllers\Api\DisponibilidadController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\PagoController;
use App\Http\Controllers\Api\PublicTipoConsultaController;
use App\Http\Controllers\Api\Webhooks\DailyWebhookController;
use App\Http\Controllers\Api\Webhooks\StripeWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('health', HealthController::class);

Route::prefix('webhooks')->group(function () {
    Route::post('stripe', StripeWebhookController::class);
    Route::post('daily', DailyWebhookController::class);
});

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('password.email');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('password.reset');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
            ->middleware('signed')
            ->name('verification.verify');
    });
});

Route::prefix('public')->group(function () {
    Route::get('tipos-consulta', [PublicTipoConsultaController::class, 'index']);
    Route::get('tipos-consulta/{slug}', [PublicTipoConsultaController::class, 'show']);
    Route::get('tipos-consulta/{slug}/disponibilidad-rapida', [DisponibilidadController::class, 'quickBySlug']);
    Route::get('disponibilidad', [DisponibilidadController::class, 'index']);
});

Route::get('disponibilidad', [DisponibilidadController::class, 'index']);

Route::middleware('auth:sanctum')->prefix('tipos-consulta')->group(function () {
    Route::get('/', [PublicTipoConsultaController::class, 'index']);
    Route::get('{slug}', [PublicTipoConsultaController::class, 'show']);
    Route::get('{slug}/disponibilidad-rapida', [DisponibilidadController::class, 'quickBySlug']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('citas', [CitaController::class, 'index']);
    Route::post('citas', [CitaController::class, 'store']);
    Route::post('citas/{uuid}/extender-reserva', [CitaController::class, 'extenderReserva']);
    Route::post('citas/{uuid}/pagar/stripe', [CitaController::class, 'crearPaymentIntentStripe']);
    Route::post('citas/{uuid}/pagar/transferencia/comprobante', [CitaController::class, 'subirComprobanteTransferencia']);

    Route::get('pagos', [PagoController::class, 'index']);
    Route::get('pagos/comprobantes', [ComprobanteTransferenciaController::class, 'index']);
    Route::get('pagos/comprobantes/{uuid}', [ComprobanteTransferenciaController::class, 'show']);
    Route::patch('pagos/comprobantes/{uuid}/validacion-manual', [ComprobanteTransferenciaController::class, 'validarManual']);
    Route::get('admin/comprobantes/metricas', [ComprobanteTransferenciaController::class, 'metrics']);
    Route::post('pagos/abono', [PagoController::class, 'pagarAbono']);
    Route::post('pagos/transferencia/validar', [PagoController::class, 'validarTransferencia']);
    Route::get('pagos/{uuid}', [PagoController::class, 'show']);
    Route::post('admin/comprobantes/{id}/aprobar', [ComprobanteTransferenciaController::class, 'aprobar']);
    Route::post('admin/comprobantes/{id}/rechazar', [ComprobanteTransferenciaController::class, 'rechazar']);
    Route::get('admin/settings/transfer-validation', [AdminTransferValidationSettingController::class, 'show']);
    Route::get('admin/settings/transfer-validation/audits', [AdminTransferValidationSettingController::class, 'audits']);
    Route::get('admin/settings/transfer-validation/audits/export', [AdminTransferValidationSettingController::class, 'exportAudits']);
    Route::post('admin/settings/transfer-validation/audits/{auditId}/revert', [AdminTransferValidationSettingController::class, 'revertAudit']);
    Route::patch('admin/settings/transfer-validation', [AdminTransferValidationSettingController::class, 'update']);

    Route::get('user', function (Request $request) {
        return $request->user();
    });
});
