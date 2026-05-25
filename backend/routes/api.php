<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AdminDisponibilidadController;
use App\Http\Controllers\Api\AgenteController;
use App\Http\Controllers\Api\AdminClientesController;
use App\Http\Controllers\Api\AdminCuponController;
use App\Http\Controllers\Api\AdminResenaController;
use App\Http\Controllers\Api\AdminSesionRapidaController;
use App\Http\Controllers\Api\ConsentimientoController;
use App\Http\Controllers\Api\CreditoClienteController;
use App\Http\Controllers\Api\TwoFactorAuthController;
use App\Http\Controllers\Api\PublicEspecialistasController;
use App\Http\Controllers\Api\SuperAdminEspecialistasController;
use App\Http\Controllers\Api\AdminMetricasController;
use App\Http\Controllers\Api\AdminTipoConsultaController;
use App\Http\Controllers\Api\AdminTipoConsultaPrecioController;
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
use App\Http\Controllers\Api\VideollamadaController;
use App\Http\Controllers\Api\AdminTranscripcionController;
use App\Http\Controllers\Api\ResenaController;
use App\Http\Controllers\Api\EspecialistaDashboardController;
use App\Http\Controllers\Api\AdminAuditLogController;
use App\Http\Controllers\Api\AdminNotificacionController;
use App\Http\Controllers\Api\AdminPaqueteController;
use App\Http\Controllers\Api\AdminPlantillaController;
use App\Http\Controllers\Api\AdminReportesController;
use App\Http\Controllers\Api\AdminSupportTicketController;
use App\Http\Controllers\Api\MonedaController;
use App\Http\Controllers\Api\SupportTicketController;
use App\Http\Controllers\Api\CuponController;
use App\Http\Controllers\Api\MembresiaController;
use App\Http\Controllers\Api\SeoController;
use App\Http\Controllers\Api\Webhooks\DailyWebhookController;
use App\Http\Controllers\Api\Webhooks\ResendWebhookController;
use App\Http\Controllers\Api\Webhooks\StripeWebhookController;
use App\Http\Controllers\Api\Webhooks\WhatsappWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('health', HealthController::class);
Route::get('sitemap.xml', [SeoController::class, 'sitemap']);
Route::get('robots.txt', [SeoController::class, 'robots']);

Route::prefix('webhooks')->group(function () {
    Route::post('stripe', StripeWebhookController::class);
    // FASE 2 — Flow.cl webhook: Route::post('flow', \App\Http\Controllers\Api\Webhooks\FlowWebhookController::class);
    Route::post('paypal', \App\Http\Controllers\Api\Webhooks\PaypalWebhookController::class);
    Route::post('daily', DailyWebhookController::class);
    Route::get('whatsapp', [WhatsappWebhookController::class, 'verify']);
    Route::post('whatsapp', WhatsappWebhookController::class);
    Route::post('resend', ResendWebhookController::class);
});

Route::prefix('auth')->group(function () {
    Route::get('google', [AuthController::class, 'googleRedirect']);
    Route::get('google/callback', [AuthController::class, 'googleCallback']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('password.email');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('password.reset');
    Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware('signed')
        ->name('verification.verify');
    Route::middleware('auth:sanctum')->post('resend-verification', [AuthController::class, 'resendVerification']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

// Agente IA publico (sin auth, throttle estricto contra abuso)
Route::middleware('throttle:10,1')->post('agente/publico', [AgenteController::class, 'consultarPublico']);
Route::middleware('throttle:6,1')->post('soporte', [SupportTicketController::class, 'storePublic']);

// Confirmacion de asistencia via email (signed URL, sin auth)
Route::get('citas/{uuid}/confirmar-asistencia', [CitaController::class, 'confirmarAsistencia'])
    ->name('citas.confirmar-asistencia');
Route::get('citas/{uuid}/aceptar-reprogramacion', [CitaController::class, 'aceptarReprogramacion'])
    ->name('citas.aceptar-reprogramacion');

Route::prefix('public')->group(function () {
    Route::get('tipos-consulta', [PublicTipoConsultaController::class, 'index']);
    Route::get('tipos-consulta/{slug}', [PublicTipoConsultaController::class, 'show']);
    Route::get('tipos-consulta/{slug}/disponibilidad-rapida', [DisponibilidadController::class, 'quickBySlug']);
    Route::get('disponibilidad', [DisponibilidadController::class, 'index']);
    Route::get('especialistas', [PublicEspecialistasController::class, 'index']);
    Route::get('especialistas/{slug}', [PublicEspecialistasController::class, 'show']);
    Route::get('especialistas/{slug}/resenas', [ResenaController::class, 'indexPublic']);
});

Route::get('disponibilidad', [DisponibilidadController::class, 'index']);

// Cuentas bancarias públicas (para mostrar al cliente al pagar por transferencia)
Route::get('cuentas-bancarias', [\App\Http\Controllers\Api\CuentaBancariaController::class, 'indexPublico']);

// Moneda (público): detección por país (CF-IPCountry) + conversión
Route::get('moneda/detectar', [MonedaController::class, 'detectar']);
Route::post('moneda/convertir', [MonedaController::class, 'convertir']);

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
    Route::post('admin/citas/{uuid}/reprogramar', [CitaController::class, 'reprogramarAdmin']);
    Route::post('admin/citas/{uuid}/marcar-saldo-pagado', [CitaController::class, 'marcarSaldoPagadoManual']);
    Route::post('admin/sesiones-rapidas', [AdminSesionRapidaController::class, 'store'])->middleware('admin');
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
    Route::post('citas/{uuid}/resena', [ResenaController::class, 'store']);
    Route::get('citas/{uuid}/resena', [ResenaController::class, 'miResena']);
    Route::post('citas', [CitaController::class, 'store']);
    Route::post('citas/{uuid}/extender-reserva', [CitaController::class, 'extenderReserva']);
    Route::post('citas/{uuid}/pagar/paypal',           [CitaController::class, 'crearPagoPaypal']);
    Route::post('citas/{uuid}/pagar/paypal/confirmar', [CitaController::class, 'confirmarPagoPaypal']);
    // FASE 2 — Flow.cl: Route::post('citas/{uuid}/pagar/flow', [CitaController::class, 'crearPagoFlow']);
    Route::get('citas/{uuid}/pagar/transferencia/datos', [CitaController::class, 'datosTransferencia']);
    Route::post('citas/{uuid}/pagar/transferencia/comprobante', [CitaController::class, 'subirComprobanteTransferencia']);
    Route::get('grabaciones/{uuid}/url', [GrabacionController::class, 'obtenerUrlFirmada']);
    Route::post('grabaciones/{uuid}/descarga', [GrabacionController::class, 'registrarDescarga']);

    // Daily.co videollamadas
    Route::get('me/citas/{uuid}/sala-video', [VideollamadaController::class, 'entrar']);
    Route::get('me/citas/{uuid}/sala-video/presencia', [VideollamadaController::class, 'presencia']);
    Route::post('me/citas/{uuid}/sala-video/grabacion/iniciar', [VideollamadaController::class, 'iniciarGrabacion']);
    Route::post('me/citas/{uuid}/sala-video/grabacion/detener', [VideollamadaController::class, 'detenerGrabacion']);

    // Admin transcripciones (texto crudo)
    Route::get('admin/citas/{uuid}/transcripcion', [AdminTranscripcionController::class, 'show']);
    Route::get('admin/citas/{uuid}/transcripcion/descargar', [AdminTranscripcionController::class, 'descargar']);

    Route::get('pagos', [PagoController::class, 'index']);
    Route::get('pagos/comprobantes', [ComprobanteTransferenciaController::class, 'index']);
    Route::get('pagos/comprobantes/{uuid}', [ComprobanteTransferenciaController::class, 'show']);
    Route::get('pagos/comprobantes/{uuid}/archivo', [ComprobanteTransferenciaController::class, 'archivo']);
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
    Route::post('me/completar-perfil', [AccountController::class, 'completarPerfil']);
    Route::delete('me/account', [MeController::class, 'deleteAccount']);
    Route::get('preferencias-notificacion', [AccountController::class, 'getNotificationPrefs']);
    Route::put('preferencias-notificacion', [AccountController::class, 'updateNotificationPrefs']);
    Route::post('account/password', [AccountController::class, 'changePassword']);
    Route::get('me/soporte', [SupportTicketController::class, 'mine']);
    Route::post('me/soporte', [SupportTicketController::class, 'storeAuthenticated']);
    Route::get('me/soporte/{uuid}', [SupportTicketController::class, 'showMine']);
    Route::post('me/soporte/{uuid}/responder', [SupportTicketController::class, 'replyMine']);

    Route::get('admin/metricas', [AdminMetricasController::class, 'index'])->middleware('admin');

    Route::middleware(['admin', 'audit'])->group(function () {
        // Horario base
        Route::get('admin/disponibilidad/horario', [AdminDisponibilidadController::class, 'indexHorario']);
        Route::put('admin/disponibilidad/horario', [AdminDisponibilidadController::class, 'upsertHorario']);

        // Bloqueos
        Route::get('admin/disponibilidad/bloqueos', [AdminDisponibilidadController::class, 'indexBloqueos']);
        Route::post('admin/disponibilidad/bloqueos', [AdminDisponibilidadController::class, 'storeBloqueo']);
        Route::post('admin/disponibilidad/bloqueos/seed-feriados-chile', [AdminDisponibilidadController::class, 'seedFeriadosChile']);
        Route::patch('admin/disponibilidad/bloqueos/{id}', [AdminDisponibilidadController::class, 'updateBloqueo']);
        Route::delete('admin/disponibilidad/bloqueos/{id}', [AdminDisponibilidadController::class, 'destroyBloqueo']);

        // Precios multi-moneda
        Route::get('admin/tipos-consulta/{id}/precios', [AdminTipoConsultaPrecioController::class, 'index']);
        Route::put('admin/tipos-consulta/{id}/precios', [AdminTipoConsultaPrecioController::class, 'upsert']);
    });

    Route::middleware('super_admin')->prefix('admin/especialistas')->group(function () {
        Route::get('/', [SuperAdminEspecialistasController::class, 'index']);
        Route::post('/', [SuperAdminEspecialistasController::class, 'store']);
        Route::post('{id}/reset-password', [SuperAdminEspecialistasController::class, 'resetPassword']);
        Route::put('{id}', [SuperAdminEspecialistasController::class, 'update']);
        Route::patch('{id}/toggle', [SuperAdminEspecialistasController::class, 'toggle']);
    });

    // Super admin: dashboard de consumo de APIs externas (Anthropic, Daily, ...)
    Route::middleware('super_admin')->group(function () {
        Route::get('admin/api-usage',          [\App\Http\Controllers\Api\AdminApiUsageController::class, 'index']);
        Route::patch('admin/api-usage/limits', [\App\Http\Controllers\Api\AdminApiUsageController::class, 'updateLimits']);
        Route::post('admin/api-usage/check',   [\App\Http\Controllers\Api\AdminApiUsageController::class, 'check']);
        // Audit log unificado — solo super_admin puede verlo
        Route::get('admin/audit-logs', [AdminAuditLogController::class, 'unified']);
        Route::get('admin/soporte', [AdminSupportTicketController::class, 'index']);
        Route::get('admin/soporte/{uuid}', [AdminSupportTicketController::class, 'show']);
        Route::patch('admin/soporte/{uuid}', [AdminSupportTicketController::class, 'update']);
        Route::get('admin/soporte/{uuid}/adjuntos/{attachmentId}', [AdminSupportTicketController::class, 'attachment']);
    });

    Route::middleware('admin')->prefix('admin/tipos-consulta')->group(function () {
        Route::get('/', [AdminTipoConsultaController::class, 'index']);
        Route::post('/', [AdminTipoConsultaController::class, 'store']);
        Route::put('{id}', [AdminTipoConsultaController::class, 'update']);
        Route::patch('reorder', [AdminTipoConsultaController::class, 'reorder']);
        Route::patch('{id}/toggle', [AdminTipoConsultaController::class, 'toggle']);
        Route::post('{id}/imagen', [AdminTipoConsultaController::class, 'uploadImagen']);
        Route::delete('{id}/imagen', [AdminTipoConsultaController::class, 'deleteImagen']);
    });

    Route::get('especialista/mis-citas', [EspecialistaDashboardController::class, 'misCitas']);

    // Cuentas bancarias del especialista (gestión propia)
    Route::middleware('admin')->prefix('admin/cuentas-bancarias')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\CuentaBancariaController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\CuentaBancariaController::class, 'store']);
        Route::patch('{id}', [\App\Http\Controllers\Api\CuentaBancariaController::class, 'update']);
        Route::delete('{id}', [\App\Http\Controllers\Api\CuentaBancariaController::class, 'destroy']);
    });

    Route::middleware('admin')->group(function () {
        Route::get('admin/reportes/ingresos',   [AdminReportesController::class, 'ingresos']);
        Route::get('admin/reportes/consultas',  [AdminReportesController::class, 'consultas']);
        Route::get('admin/reportes/clientes',   [AdminReportesController::class, 'clientes']);
        Route::get('admin/reportes/fiscal',     [AdminReportesController::class, 'fiscal']);
        Route::get('admin/reportes/export',     [AdminReportesController::class, 'exportCsv']);

        // Admin: paquetes
        Route::prefix('admin/paquetes')->group(function () {
            Route::get('/', [AdminPaqueteController::class, 'index']);
            Route::post('/', [AdminPaqueteController::class, 'store']);
            Route::get('{id}', [AdminPaqueteController::class, 'show']);
            Route::patch('{id}', [AdminPaqueteController::class, 'update']);
            Route::delete('{id}', [AdminPaqueteController::class, 'destroy']);
            Route::post('{id}/toggle', [AdminPaqueteController::class, 'toggle']);
        });

        // Admin: plantillas de notificación
        Route::prefix('admin/plantillas')->group(function () {
            Route::get('/', [AdminPlantillaController::class, 'index']);
            Route::post('/', [AdminPlantillaController::class, 'store']);
            Route::get('{id}', [AdminPlantillaController::class, 'show']);
            Route::patch('{id}', [AdminPlantillaController::class, 'update']);
            Route::delete('{id}', [AdminPlantillaController::class, 'destroy']);
            Route::post('{id}/preview', [AdminPlantillaController::class, 'preview']);
        });

        // Admin: monitor notificaciones enviadas
        Route::get('admin/notificaciones', [AdminNotificacionController::class, 'index']);
        Route::get('admin/notificaciones/metrics', [AdminNotificacionController::class, 'metrics']);

        // Admin: visor audit log de configuraciones (legacy)
        Route::get('admin/audit-log', [AdminAuditLogController::class, 'index']);
    });

    // Cupones
    Route::post('cupones/validar', [CuponController::class, 'validar']);
    Route::post('citas/{uuid}/aplicar-cupon', [CuponController::class, 'aplicar']);

    // Membresía
    Route::get('membresia', [MembresiaController::class, 'show']);
    Route::post('citas/{uuid}/aplicar-membresia', [MembresiaController::class, 'aplicar']);

    // Agente IA conversacional
    Route::middleware('throttle:30,1')->group(function () {
        Route::post('me/agente/consultar', [AgenteController::class, 'consultarSelf']);
        Route::get('me/agente/conversaciones', [AgenteController::class, 'indexSelf']);
        Route::middleware('admin')->group(function () {
            Route::post('agente/consultar', [AgenteController::class, 'consultarAdmin']);
            Route::get('agente/conversaciones', [AgenteController::class, 'indexAdmin']);
            Route::get('admin/agente/metrics', [\App\Http\Controllers\Api\AdminAgenteMetricsController::class, 'index']);
        });
    });

    // Admin: gestion de clientes
    Route::middleware('admin')->prefix('admin/clientes')->group(function () {
        Route::get('/', [AdminClientesController::class, 'index']);
        Route::get('{uuid}', [AdminClientesController::class, 'show']);
        Route::get('{uuid}/estadisticas', [AdminClientesController::class, 'estadisticas']);
        Route::patch('{uuid}/notas', [AdminClientesController::class, 'actualizarNotas']);
        Route::post('{uuid}/reset-password', [AdminClientesController::class, 'resetPassword'])->middleware('super_admin');
        Route::middleware('throttle:20,1')->get('{uuid}/briefing', [AdminClientesController::class, 'briefing']);
        Route::delete('{uuid}', [AdminClientesController::class, 'destroy']);
    });

    // Admin: gestion de cupones
    Route::middleware('admin')->prefix('admin/cupones')->group(function () {
        Route::get('/', [AdminCuponController::class, 'index']);
        Route::post('/', [AdminCuponController::class, 'store']);
        Route::get('{codigo}', [AdminCuponController::class, 'show']);
        Route::patch('{codigo}', [AdminCuponController::class, 'update']);
        Route::delete('{codigo}', [AdminCuponController::class, 'destroy']);
        Route::post('{codigo}/toggle', [AdminCuponController::class, 'toggle']);
    });

    // Admin: gestion de resenas
    Route::middleware('admin')->prefix('admin/resenas')->group(function () {
        Route::get('/', [AdminResenaController::class, 'index']);
        Route::get('{uuid}', [AdminResenaController::class, 'show']);
        Route::post('{uuid}/responder', [AdminResenaController::class, 'responder']);
        Route::delete('{uuid}/responder', [AdminResenaController::class, 'eliminarRespuesta']);
        Route::post('{uuid}/visibilidad', [AdminResenaController::class, 'toggleVisibilidad']);
    });

    // Creditos de cliente (autoservicio + admin)
    Route::get('me/creditos', [CreditoClienteController::class, 'misCreditos']);
    Route::middleware('admin')->group(function () {
        Route::get('admin/clientes/{uuid}/creditos', [CreditoClienteController::class, 'indexAdmin']);
        Route::post('admin/clientes/{uuid}/creditos', [CreditoClienteController::class, 'storeAdmin']);
        Route::post('admin/creditos/{id}/anular', [CreditoClienteController::class, 'anularAdmin']);
    });

    // 2FA
    Route::prefix('me/2fa')->group(function () {
        Route::get('status', [TwoFactorAuthController::class, 'status']);
        Route::post('setup', [TwoFactorAuthController::class, 'setup']);
        Route::post('confirm', [TwoFactorAuthController::class, 'confirm']);
        Route::post('verify', [TwoFactorAuthController::class, 'verify']);
        Route::post('recovery-codes/regenerate', [TwoFactorAuthController::class, 'regenerateRecoveryCodes']);
        Route::post('disable', [TwoFactorAuthController::class, 'disable']);
    });

    // Consentimientos GDPR
    Route::get('me/consentimientos', [ConsentimientoController::class, 'index']);
    Route::post('me/consentimientos', [ConsentimientoController::class, 'store']);

    Route::get('user', function (Request $request) {
        $user = $request->user()->load(['profile', 'roles']);
        $arr = $user->toArray();
        $arr['perfil_completo'] = $user->profile ? $user->profile->estaCompleto() : false;
        return $arr;
    });
});
