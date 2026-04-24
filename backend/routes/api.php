<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminTransferValidationSettingController;
use App\Http\Controllers\Api\CitaController;
use App\Http\Controllers\Api\ComprobanteTransferenciaController;
use App\Http\Controllers\Api\DisponibilidadController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\PagoController;
use App\Http\Controllers\Api\PublicTipoConsultaController;
use App\Http\Controllers\Api\ReembolsoController;
use App\Http\Controllers\Api\GrabacionController;
use App\Http\Controllers\Api\Webhooks\DailyWebhookController;
use App\Http\Controllers\Api\Webhooks\ResendWebhookController;
use App\Http\Controllers\Api\Webhooks\StripeWebhookController;
use App\Http\Controllers\Api\Webhooks\WhatsappWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('health', HealthController::class);

Route::prefix('webhooks')->group(function () {
    Route::post('stripe', StripeWebhookController::class);
    Route::post('daily', DailyWebhookController::class);
    Route::get('whatsapp', [WhatsappWebhookController::class, 'verify']);
    Route::post('whatsapp', WhatsappWebhookController::class);
    Route::post('resend', ResendWebhookController::class);
});

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('password.email');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('password.reset');
    Route::middleware('auth:sanctum')->post('resend-verification', [AuthController::class, 'resendVerification']);

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
    Route::get('citas/{uuid}', [CitaController::class, 'show']);
    Route::get('citas/{uuid}/historial', [CitaController::class, 'historial']);
    Route::get('citas/{uuid}/calendario-ics', [CitaController::class, 'calendarioIcs']);
    Route::get('admin/citas/historial', [CitaController::class, 'historialAdmin']);
    Route::get('admin/citas/historial/export', [CitaController::class, 'historialAdminExport']);
    Route::post('admin/citas/{uuid}/no-show', [CitaController::class, 'marcarNoShow']);
    Route::get('me/export', [MeController::class, 'export']);
    Route::get('me/datos-personales', [MeController::class, 'datosPersonales']);
    Route::get('me/consultas', [MeController::class, 'consultas']);
    Route::get('me/consultas/{uuid}', [MeController::class, 'consultaDetalle']);
    Route::get('me/consultas/export/pdf', [MeController::class, 'exportConsultasPdf']);
    Route::get('me/consultas/{uuid}/transcripcion', [MeController::class, 'consultaTranscripcion']);
    Route::get('me/consultas/{uuid}/grabacion-url', [MeController::class, 'consultaGrabacionUrl']);
    Route::get('me/consultas/{uuid}/resumen', [MeController::class, 'consultaResumen']);
    Route::patch('me/consultas/{uuid}/notas-privadas', [MeController::class, 'actualizarNotasPrivadas']);
    Route::get('citas/{uuid}/sala', [CitaController::class, 'sala']);
    Route::post('citas/{uuid}/sala/consentimiento', [CitaController::class, 'registrarConsentimientoSala']);
    Route::post('citas/{uuid}/sala/entrada', [CitaController::class, 'marcarEntradaSala']);
    Route::post('citas/{uuid}/reagendar', [CitaController::class, 'reagendar']);
    Route::post('citas/{uuid}/cancelar', [CitaController::class, 'cancelar']);
    Route::post('citas', [CitaController::class, 'store']);
    Route::post('citas/{uuid}/extender-reserva', [CitaController::class, 'extenderReserva']);
    Route::post('citas/{uuid}/pagar/stripe', [CitaController::class, 'crearPaymentIntentStripe']);
    Route::get('citas/{uuid}/pagar/transferencia/datos', [CitaController::class, 'datosTransferencia']);
    Route::post('citas/{uuid}/pagar/transferencia/comprobante', [CitaController::class, 'subirComprobanteTransferencia']);
    Route::get('grabaciones/{uuid}/url', [GrabacionController::class, 'obtenerUrlFirmada']);
    Route::post('grabaciones/{uuid}/descarga', [GrabacionController::class, 'registrarDescarga']);

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
    Route::get('admin/reembolsos/metricas', [ReembolsoController::class, 'metrics']);
    Route::get('admin/reembolsos/export', [ReembolsoController::class, 'export']);
    Route::get('admin/reembolsos/{uuid}', [ReembolsoController::class, 'show']);
    Route::get('admin/reembolsos', [ReembolsoController::class, 'index']);
    Route::post('admin/reembolsos', [ReembolsoController::class, 'store']);
    Route::post('admin/reembolsos/{uuid}/procesar', [ReembolsoController::class, 'procesar']);
    Route::get('admin/settings/transfer-validation', [AdminTransferValidationSettingController::class, 'show']);
    Route::get('admin/settings/transfer-validation/audits', [AdminTransferValidationSettingController::class, 'audits']);
    Route::get('admin/settings/transfer-validation/audits/export', [AdminTransferValidationSettingController::class, 'exportAudits']);
    Route::post('admin/settings/transfer-validation/audits/{auditId}/revert', [AdminTransferValidationSettingController::class, 'revertAudit']);
    Route::patch('admin/settings/transfer-validation', [AdminTransferValidationSettingController::class, 'update']);

    Route::put('account/profile', [AccountController::class, 'updateProfile']);
    Route::get('preferencias-notificacion', [AccountController::class, 'getNotificationPrefs']);
    Route::put('preferencias-notificacion', [AccountController::class, 'updateNotificationPrefs']);
    Route::post('account/password', [AccountController::class, 'changePassword']);

    Route::get('user', function (Request $request) {
        return $request->user()->load(['profile', 'roles']);
    });
});
