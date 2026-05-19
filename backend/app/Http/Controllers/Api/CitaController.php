<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ValidarComprobanteJob;
use App\Mail\CitaCanceladaMail;
use App\Mail\NuevoComprobanteRecibidoMail;
use App\Models\Cita;
use App\Models\ComprobanteTransferencia;
use App\Models\Consentimiento;
use App\Models\AppSetting;
use App\Models\Pago;
use App\Models\Reagendamiento;
use App\Models\Reembolso;
use App\Models\Role;
use App\Models\TipoConsulta;
use App\Models\User;
use App\Models\ValidacionAgente;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CitaController extends Controller
{
    private const MIN_BOOKING_LEAD_MINUTES = 60;
    private const SERVICE_BUFFER_MINUTES = 15;
    private const MINUTOS_VENTANA_TRANSFERENCIA_KEY = 'MINUTOS_VENTANA_TRANSFERENCIA';
    private const MINUTOS_EXTENSION_TRANSFERENCIA_KEY = 'MINUTOS_EXTENSION_TRANSFERENCIA';
    private const EXTENSIONES_PERMITIDAS_KEY = 'EXTENSIONES_PERMITIDAS';
    private const TRANSFERENCIA_BANCO_KEY = 'TRANSFERENCIA_BANCO';
    private const TRANSFERENCIA_TITULAR_KEY = 'TRANSFERENCIA_TITULAR';
    private const TRANSFERENCIA_CUENTA_KEY = 'TRANSFERENCIA_CUENTA';
    private const TRANSFERENCIA_TIPO_CUENTA_KEY = 'TRANSFERENCIA_TIPO_CUENTA';
    private const TRANSFERENCIA_RUT_KEY = 'TRANSFERENCIA_RUT';
    private const TRANSFERENCIA_EMAIL_KEY = 'TRANSFERENCIA_EMAIL';
    private const HORAS_REEMBOLSO_ANTICIPACION_KEY = 'HORAS_REEMBOLSO_ANTICIPACION';
    private const MAX_CANCELACIONES_CON_REEMBOLSO_CLIENTE_KEY = 'MAX_CANCELACIONES_CON_REEMBOLSO_CLIENTE';

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $citas = Cita::query()
            ->with('tipoConsulta:id,slug,nombre,duracion_minutos')
            ->where('cliente_id', $user->id)
            ->orderByDesc('inicio_utc')
            ->get();

        return response()->json([
            'data' => $citas,
        ]);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $cita = Cita::query()
            ->with([
                'tipoConsulta:id,slug,nombre,duracion_minutos',
                'pagos:id,cita_id,uuid,tipo,canal,monto_centavos,moneda,estado,pagado_en,created_at',
                'grabaciones.transcripcion.resumen',
            ])
            ->where('uuid', $uuid)
            ->where('cliente_id', $user->id)
            ->firstOrFail();

        return response()->json([
            'data' => $cita,
        ]);
    }

    public function historial(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $cita = Cita::query()
            ->with([
                'tipoConsulta:id,slug,nombre,duracion_minutos',
                'grabaciones.transcripcion.resumen',
            ])
            ->where('uuid', $uuid)
            ->where('cliente_id', $user->id)
            ->firstOrFail();

        return response()->json([
            'data' => [
                'cita' => $cita,
                'historial' => $cita->grabaciones->map(function ($grabacion) {
                    return [
                        'grabacion' => [
                            'id' => $grabacion->id,
                            'daily_recording_id' => $grabacion->daily_recording_id,
                            'daily_room_name' => $grabacion->daily_room_name,
                            'url_grabacion' => $grabacion->url_grabacion,
                            'estado' => $grabacion->estado,
                            'transcripcion_procesada_en' => $grabacion->transcripcion_procesada_en,
                            'resumen_generado_en' => $grabacion->resumen_generado_en,
                        ],
                        'transcripcion' => $grabacion->transcripcion,
                        'resumen' => $grabacion->transcripcion ? $grabacion->transcripcion->resumen : null,
                    ];
                })->values(),
            ],
        ]);
    }

    public function calendarioIcs(Request $request, string $uuid)
    {
        /** @var User $user */
        $user = $request->user();

        $cita = Cita::query()
            ->with('tipoConsulta:id,slug,nombre,duracion_minutos')
            ->where('uuid', $uuid)
            ->where('cliente_id', $user->id)
            ->firstOrFail();

        $dtStampUtc = now()->copy()->utc()->format('Ymd\THis\Z');
        $dtStartUtc = optional($cita->inicio_utc)->copy()->utc()->format('Ymd\THis\Z');
        $dtEndUtc = optional($cita->fin_utc)->copy()->utc()->format('Ymd\THis\Z');

        $summary = $this->escapeIcsText('Consulta Tarot Estrellas - '.optional($cita->tipoConsulta)->nombre);
        $description = $this->escapeIcsText(sprintf(
            "Codigo de referencia: %s\nTipo de consulta: %s\nEstado: %s",
            (string) $cita->codigo_referencia,
            (string) optional($cita->tipoConsulta)->nombre,
            (string) $cita->estado
        ));

        $uid = $cita->uuid.'@tarotestrellas.com';

        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Tarot Estrellas//Citas//ES',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$uid,
            'DTSTAMP:'.$dtStampUtc,
            'DTSTART:'.$dtStartUtc,
            'DTEND:'.$dtEndUtc,
            'SUMMARY:'.$summary,
            'DESCRIPTION:'.$description,
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        ]);

        $filename = 'cita-'.$cita->uuid.'.ics';

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function sala(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $cita = Cita::query()
            ->with('tipoConsulta:id,slug,nombre,duracion_minutos')
            ->where('uuid', $uuid)
            ->where('cliente_id', $user->id)
            ->firstOrFail();

        $now = now();
        $abre = $cita->inicio_utc?->copy()->subMinutes(15);
        $cierra = $cita->fin_utc?->copy()->addMinutes(30);
        $enHorario = $abre && $cierra && $now->gte($abre) && $now->lte($cierra);

        $pagoCompletado = in_array($cita->estado_pago, ['pagado', 'aprobado'], true)
            || in_array($cita->estado, ['pagada', 'confirmada', 'en_curso'], true);

        $url = (string) ($cita->daily_room_url ?? '');
        $token = '';
        $salaCreada = false;

        if ($pagoCompletado && $enHorario) {
            try {
                /** @var \App\Services\DailyRoomService $daily */
                $daily = app(\App\Services\DailyRoomService::class);
                $sala = $daily->crearSala($cita);
                $cita->refresh();
                $url = $sala['room_url'];
                $token = $daily->crearMeetingToken($cita, $user, isOwner: false);
                $salaCreada = true;
            } catch (\Throwable $e) {
                $salaCreada = false;
            }
        }

        return response()->json([
            'data' => [
                'url' => $url,
                'token' => $token,
                'sala_creada' => $salaCreada,
                'pago_completado' => $pagoCompletado,
                'en_horario' => $enHorario,
                'recording_enabled' => (bool) $cita->grabacion_solicitada,
                'requires_recording_consent' => (bool) $cita->grabacion_solicitada,
                'cita' => [
                    'uuid' => $cita->uuid,
                    'inicio_utc' => optional($cita->inicio_utc)->toIso8601String(),
                    'fin_utc' => optional($cita->fin_utc)->toIso8601String(),
                    'tipo_consulta' => $cita->tipoConsulta ? [
                        'nombre' => $cita->tipoConsulta->nombre,
                        'duracion_minutos' => (int) $cita->tipoConsulta->duracion_minutos,
                    ] : null,
                ],
            ],
        ]);
    }

    public function registrarConsentimientoSala(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'acepta_grabacion' => ['required', 'boolean'],
            'version_documento' => ['nullable', 'string', 'max:30'],
        ]);

        if (! $validated['acepta_grabacion']) {
            return response()->json([
                'message' => 'Debes aceptar el consentimiento para ingresar a la sala.',
            ], 422);
        }

        /** @var User $user */
        $user = $request->user();

        $cita = Cita::query()
            ->where('uuid', $uuid)
            ->where('cliente_id', $user->id)
            ->firstOrFail();

        $consentimiento = Consentimiento::query()->create([
            'user_id' => $user->id,
            'tipo' => 'grabacion_sala',
            'version_documento' => $validated['version_documento'] ?? 'v1',
            'otorgado' => true,
            'otorgado_en' => now(),
            'ip_otorgamiento' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
        ]);

        return response()->json([
            'message' => 'Consentimiento registrado correctamente.',
            'data' => [
                'cita_uuid' => $cita->uuid,
                'consentimiento_id' => $consentimiento->id,
                'otorgado_en' => optional($consentimiento->otorgado_en)->toIso8601String(),
            ],
        ], 201);
    }

    public function marcarEntradaSala(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $cita = Cita::query()
            ->where('uuid', $uuid)
            ->where('cliente_id', $user->id)
            ->firstOrFail();

        if (! in_array($cita->estado, ['reservada', 'confirmada', 'en_curso'], true)) {
            return response()->json([
                'message' => 'La cita no esta en estado valido para marcar entrada.',
            ], 422);
        }

        if ($cita->estado !== 'en_curso') {
            $cita->forceFill([
                'estado' => 'en_curso',
            ])->save();
        }

        return response()->json([
            'message' => 'Entrada a sala registrada.',
            'data' => [
                'cita_uuid' => $cita->uuid,
                'estado' => $cita->estado,
                'entrada_en' => now()->toIso8601String(),
            ],
        ]);
    }

    public function historialAdmin(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->isAdmin()) {
            return response()->json([
                'message' => 'No autorizado para consultar historial administrativo.',
            ], 403);
        }

        $validated = $this->validateHistorialAdminFilters($request);

        $query = $this->buildHistorialAdminQuery($validated);

        $perPage = (int) ($validated['per_page'] ?? 15);

        return response()->json($query->paginate($perPage));
    }

    public function historialAdminExport(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->isAdmin()) {
            return response()->json([
                'message' => 'No autorizado para exportar historial administrativo.',
            ], 403);
        }

        $validated = $this->validateHistorialAdminFilters($request);

        $rows = $this->buildHistorialAdminQuery($validated)
            ->limit(5000)
            ->get();

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, [
            'uuid',
            'codigo_referencia',
            'estado',
            'inicio_utc',
            'fin_utc',
            'cliente_email',
            'cliente_nombre',
            'tipo_consulta_slug',
            'tipo_consulta_nombre',
            'total_grabaciones',
            'total_transcripciones',
            'total_resumenes',
        ]);

        foreach ($rows as $row) {
            $totalGrabaciones = $row->grabaciones->count();
            $totalTranscripciones = $row->grabaciones->filter(fn ($g) => $g->transcripcion !== null)->count();
            $totalResumenes = $row->grabaciones->filter(function ($g) {
                return $g->transcripcion && $g->transcripcion->resumen !== null;
            })->count();

            fputcsv($stream, [
                $row->uuid,
                $row->codigo_referencia,
                $row->estado,
                optional($row->inicio_utc)->toDateTimeString(),
                optional($row->fin_utc)->toDateTimeString(),
                optional($row->cliente)->email,
                optional($row->cliente)->name,
                optional($row->tipoConsulta)->slug,
                optional($row->tipoConsulta)->nombre,
                $totalGrabaciones,
                $totalTranscripciones,
                $totalResumenes,
            ]);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        $filename = 'admin-citas-historial-'.now()->format('Ymd-His').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipo_consulta_slug'  => ['required', 'string', 'exists:tipos_consulta,slug'],
            'inicio_utc'          => ['nullable', 'date'],
            'inicio_local'        => ['required_without:inicio_utc', 'date_format:Y-m-d H:i:s'],
            'zona_horaria_cliente' => ['required', 'timezone'],
            'canal_pago'          => ['required', 'in:transferencia,paypal'],
            'moneda'              => ['nullable', 'string', 'size:3'],
            'tema_principal'      => ['nullable', 'string', 'max:50'],
            'notas_cliente'       => ['nullable', 'string', 'max:2000'],
            'especialista_id'     => ['nullable', 'integer', 'exists:users,id'],
            'grabacion_solicitada' => ['nullable', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if ($user->hasRole('admin_especialista') && ! $user->hasRole('super_admin')) {
            throw ValidationException::withMessages([
                'tipo_consulta_slug' => 'Los especialistas gestionan su agenda desde el panel y no pueden reservar servicios como clientes.',
            ]);
        }

        $tipo = TipoConsulta::query()
            ->where('slug', $validated['tipo_consulta_slug'])
            ->where('activo', true)
            ->firstOrFail();

        $startUtc = ! empty($validated['inicio_utc'])
            ? CarbonImmutable::parse($validated['inicio_utc'])->setTimezone('UTC')
            : CarbonImmutable::parse($validated['inicio_local'], $validated['zona_horaria_cliente'])->setTimezone('UTC');
        $endUtc = $startUtc->addMinutes((int) $tipo->duracion_minutos);

        if ($startUtc->lt(CarbonImmutable::now('UTC')->addMinutes(self::MIN_BOOKING_LEAD_MINUTES))) {
            throw ValidationException::withMessages([
                'inicio_local' => 'Debes reservar con al menos 1 hora de anticipación.',
            ]);
        }

        $collision = Cita::query()
            ->whereIn('estado', ['pendiente_abono', 'reservada', 'confirmada', 'en_curso'])
            ->where('inicio_utc', '<', $endUtc->addMinutes(self::SERVICE_BUFFER_MINUTES)->toDateTimeString())
            ->where('fin_utc', '>', $startUtc->subMinutes(self::SERVICE_BUFFER_MINUTES)->toDateTimeString())
            ->exists();

        if ($collision) {
            throw ValidationException::withMessages([
                'inicio_local' => 'El horario seleccionado ya no se encuentra disponible.',
            ]);
        }

        $moneda = strtoupper($validated['moneda'] ?? $tipo->moneda ?? 'CLP');
        $basePrice = (int) $tipo->precio_referencial_centavos;
        $grabacionSolicitada = (bool) ($validated['grabacion_solicitada'] ?? false);
        $extraGrabacion = $grabacionSolicitada
            ? \App\Support\GrabacionPricing::extraCentavos((int) $tipo->duracion_minutos, $moneda)
            : 0;
        $price = $basePrice + $extraGrabacion;
        $isPrimeraConsulta = ! Cita::query()->where('cliente_id', $user->id)->exists();

        $cita = Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => $this->generateReferenceCode(),
            'cliente_id' => $user->id,
            'especialista_id' => $validated['especialista_id'] ?? null,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => $startUtc->toDateTimeString(),
            'fin_utc' => $endUtc->toDateTimeString(),
            'duracion_minutos' => (int) $tipo->duracion_minutos,
            'zona_horaria_cliente' => $validated['zona_horaria_cliente'],
            'estado' => 'pendiente_abono',
            'canal_pago' => $validated['canal_pago'],
            'grabacion_solicitada' => $grabacionSolicitada,
            'grabacion_extra_centavos' => $extraGrabacion,
            'precio_total_centavos' => $price,
            'precio_final_centavos' => $price,
            'moneda' => $moneda,
            'tema_principal' => $validated['tema_principal'] ?? null,
            'notas_cliente' => $validated['notas_cliente'] ?? null,
            'reservada_hasta' => now()->addMinutes(
                $this->getPositiveIntSetting(self::MINUTOS_VENTANA_TRANSFERENCIA_KEY, 30)
            ),
            'extension_reserva_conteo' => 0,
            'es_primera_consulta' => $isPrimeraConsulta,
        ]);

        $cita->load('tipoConsulta:id,slug,nombre,duracion_minutos');

        return response()->json([
            'message' => 'Cita creada exitosamente.',
            'data' => $cita,
        ], 201);
    }

    public function subirComprobanteTransferencia(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'comprobante' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,txt', 'max:10240'],
            'transaccion_id' => ['nullable', 'string', 'max:100'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $cita = Cita::query()
            ->where('uuid', $uuid)
            ->where('cliente_id', $user->id)
            ->firstOrFail();

        if ($cita->canal_pago !== 'transferencia') {
            throw ValidationException::withMessages([
                'uuid' => 'La cita no corresponde a un flujo de pago por transferencia.',
            ]);
        }

        /** @var UploadedFile $file */
        $file = $validated['comprobante'];

        $comprobanteUuid = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $fileName = $comprobanteUuid.'.'.$extension;
        $path = $file->storeAs('comprobantes_transferencia/'.now()->format('Y/m'), $fileName, 'local');

        $datosExtraidos = $this->extractBasicComprobanteData($file, $path);
        if (! empty($validated['transaccion_id'])) {
            $datosExtraidos['id_transaccion'] = $validated['transaccion_id'];
        }

        $comprobante = ComprobanteTransferencia::query()->create([
            'uuid' => $comprobanteUuid,
            'cita_id' => $cita->id,
            'pago_id' => $cita->pagos()->latest('id')->value('id'),
            'archivo_url' => $path,
            'archivo_tipo' => $file->getClientMimeType() ?: 'application/octet-stream',
            'tamano_bytes' => $file->getSize(),
            'datos_extraidos' => $datosExtraidos,
            'estado_validacion' => 'pendiente',
            'id_transaccion_bancaria' => $datosExtraidos['id_transaccion'],
        ]);

        ValidacionAgente::query()->create([
            'comprobante_id' => $comprobante->id,
            'cliente_id' => $user->id,
            'cita_id' => $cita->id,
            'pago_id' => $comprobante->pago_id,
            'regla_3_referencia_ok' => ! empty($cita->codigo_referencia),
            'decision' => 'revision_manual',
            'razon' => 'Comprobante recibido y pendiente de validacion automatica/manual.',
            'modelo_ia' => 'upload-parser-v1',
            'duracion_ms' => 0,
            'payload' => [
                'archivo_tipo' => $comprobante->archivo_tipo,
                'tamano_bytes' => $comprobante->tamano_bytes,
                'archivo_path' => $path,
            ],
        ]);

        ValidarComprobanteJob::dispatch($comprobante->id);

        // Notificar a admins que hay un nuevo comprobante pendiente de revisión
        $adminRoleIds = Role::query()
            ->whereIn('nombre', ['super_admin', 'admin_especialista'])
            ->pluck('id');

        $admins = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('roles.id', $adminRoleIds))
            ->get();

        foreach ($admins as $admin) {
            Mail::to($admin->email)
                ->send(new NuevoComprobanteRecibidoMail($comprobante->load('cita.tipoConsulta'), $user));
        }

        return response()->json([
            'message' => 'Comprobante subido correctamente.',
            'data' => [
                'uuid' => $comprobante->uuid,
                'cita_uuid' => $cita->uuid,
                'estado_validacion' => $comprobante->estado_validacion,
                'id_transaccion_bancaria' => $comprobante->id_transaccion_bancaria,
                'archivo_url' => $comprobante->archivo_url,
                'datos_extraidos' => $comprobante->datos_extraidos,
            ],
        ], 201);
    }

    public function extenderReserva(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $cita = Cita::query()
            ->where('uuid', $uuid)
            ->where('cliente_id', $user->id)
            ->firstOrFail();

        if ($cita->canal_pago !== 'transferencia') {
            return response()->json([
                'message' => 'Solo se puede extender reserva en citas con pago por transferencia.',
            ], 422);
        }

        if ($cita->estado !== 'pendiente_abono') {
            return response()->json([
                'message' => 'La cita no esta en estado valido para extender reserva.',
            ], 422);
        }

        $extensionesPermitidas = $this->getPositiveIntSetting(self::EXTENSIONES_PERMITIDAS_KEY, 1);
        $extensionesAplicadas = (int) ($cita->extension_reserva_conteo ?? ($cita->extension_reserva_aplicada_en !== null ? 1 : 0));

        if ($extensionesAplicadas >= $extensionesPermitidas) {
            return response()->json([
                'message' => 'La reserva ya fue extendida anteriormente.',
            ], 422);
        }

        if (! $cita->reservada_hasta || now()->greaterThan($cita->reservada_hasta)) {
            return response()->json([
                'message' => 'La reserva ya expiro y no puede extenderse.',
            ], 422);
        }

        $minutosExtension = $this->getPositiveIntSetting(self::MINUTOS_EXTENSION_TRANSFERENCIA_KEY, 10);
        $nuevaReservaHasta = $cita->reservada_hasta->copy()->addMinutes($minutosExtension);

        $cita->forceFill([
            'reservada_hasta' => $nuevaReservaHasta,
            'extension_reserva_aplicada_en' => now(),
            'extension_reserva_conteo' => $extensionesAplicadas + 1,
        ])->save();

        return response()->json([
            'message' => sprintf('Reserva extendida por %d minutos.', $minutosExtension),
            'data' => [
                'uuid' => $cita->uuid,
                'reservada_hasta' => $cita->reservada_hasta,
                'extension_reserva_aplicada_en' => $cita->extension_reserva_aplicada_en,
                'extensiones_aplicadas' => (int) $cita->extension_reserva_conteo,
                'extensiones_permitidas' => $extensionesPermitidas,
            ],
        ]);
    }

    // =========================================================================
    // FASE 2 — Flow.cl (pasarela chilena de pago online)
    // Pendiente de implementar. Por ahora Chile opera sólo con transferencia.
    // =========================================================================
    /*
    public function crearPagoFlow(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'tipo' => ['nullable', 'in:abono_20,saldo_80'],
        ]);

        // @var User $user
        $user = $request->user();

        $cita = Cita::query()
            ->where('uuid', $uuid)
            ->where('cliente_id', $user->id)
            ->firstOrFail();

        if ($cita->canal_pago !== 'flow') {
            throw ValidationException::withMessages([
                'uuid' => 'La cita no corresponde a flujo de pago con Flow.',
            ]);
        }

        $tipoEsperado  = null;
        $montoEsperado = 0;
        $abonoObjetivo = (int) round($cita->precio_final_centavos * 0.20);
        $saldoObjetivo = max(0, (int) $cita->precio_final_centavos - $abonoObjetivo);

        if ($cita->estado === 'pendiente_abono') {
            $tipoEsperado  = 'abono_20';
            $montoEsperado = $abonoObjetivo;
        } elseif ($cita->estado === 'reservada') {
            $tipoEsperado  = 'saldo_80';
            $montoEsperado = $saldoObjetivo;
        }

        if (! $tipoEsperado || $montoEsperado <= 0) {
            throw ValidationException::withMessages([
                'uuid' => 'La cita no está en estado válido para pagar.',
            ]);
        }

        $tipoSolicitado = $validated['tipo'] ?? $tipoEsperado;
        if ($tipoSolicitado !== $tipoEsperado) {
            throw ValidationException::withMessages([
                'tipo' => 'El tipo de pago no coincide con el estado actual de la cita.',
            ]);
        }

        $flow = app(\App\Services\FlowService::class);

        if (! $flow->estaConfigurado()) {
            return response()->json(['message' => 'Flow.cl no está configurado.'], 503);
        }

        $frontendUrl   = rtrim((string) config('app.frontend_url', 'https://tarotestrellas.com'), '/');
        $commerceOrder = sprintf('cita:%s:tipo:%s', $cita->uuid, $tipoSolicitado);

        try {
            $result = $flow->crearPago(
                commerceOrder:   $commerceOrder,
                subject:         sprintf('TarotEstrellas - %s', $tipoSolicitado === 'abono_20' ? 'Abono 20%' : 'Saldo 80%'),
                amount:          $montoEsperado,
                email:           $user->email,
                urlConfirmation: url('/api/webhooks/flow'),
                urlReturn:       $frontendUrl . '/pago-resultado?cita=' . $cita->uuid,
                currency:        $cita->moneda,
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Error al crear pago en Flow: ' . $e->getMessage()], 502);
        }

        $pago = $cita->pagos()->create([
            'uuid'               => (string) Str::uuid(),
            'tipo'               => $tipoSolicitado,
            'canal'              => 'flow',
            'monto_centavos'     => $montoEsperado,
            'moneda'             => $cita->moneda,
            'estado'             => 'pendiente',
            'referencia_externa' => 'flow:' . $result['token'],
            'metadata'           => [
                'flow_token'     => $result['token'],
                'flow_order'     => $result['flow_order'],
                'commerce_order' => $commerceOrder,
            ],
        ]);

        return response()->json([
            'message' => 'Pago Flow creado correctamente.',
            'data'    => [
                'cita_uuid'      => $cita->uuid,
                'pago_uuid'      => $pago->uuid,
                'tipo'           => $tipoSolicitado,
                'monto_centavos' => $montoEsperado,
                'moneda'         => $cita->moneda,
                'redirect_url'   => $result['redirect_url'],
                'flow_token'     => $result['token'],
            ],
        ], 201);
    }
    */ // fin FASE 2 crearPagoFlow

    public function crearPagoPaypal(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'tipo'   => ['nullable', 'in:abono_20,saldo_80'],
            'moneda' => ['nullable', 'string', 'size:3'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $cita = Cita::query()
            ->where('uuid', $uuid)
            ->where('cliente_id', $user->id)
            ->firstOrFail();

        if ($cita->canal_pago !== 'paypal') {
            throw ValidationException::withMessages([
                'uuid' => 'La cita no corresponde a flujo de pago con PayPal.',
            ]);
        }

        $tipoEsperado  = null;
        $montoEsperado = 0;
        $abonoObjetivo = (int) round($cita->precio_final_centavos * 0.20);
        $saldoObjetivo = max(0, (int) $cita->precio_final_centavos - $abonoObjetivo);

        if ($cita->estado === 'pendiente_abono') {
            $tipoEsperado  = 'abono_20';
            $montoEsperado = $abonoObjetivo;
        } elseif ($cita->estado === 'reservada') {
            $tipoEsperado  = 'saldo_80';
            $montoEsperado = $saldoObjetivo;
        }

        if (! $tipoEsperado || $montoEsperado <= 0) {
            throw ValidationException::withMessages([
                'uuid' => 'La cita no está en estado válido para pagar.',
            ]);
        }

        $tipoSolicitado = $validated['tipo'] ?? $tipoEsperado;
        if ($tipoSolicitado !== $tipoEsperado) {
            throw ValidationException::withMessages([
                'tipo' => 'El tipo de pago no coincide con el estado actual de la cita.',
            ]);
        }

        $paypal = app(\App\Services\PaypalService::class);

        if (! $paypal->estaConfigurado()) {
            return response()->json(['message' => 'PayPal no está configurado.'], 503);
        }

        $frontendUrl = rtrim((string) config('app.frontend_url', 'https://tarotestrellas.com'), '/');
        $moneda      = strtoupper($validated['moneda'] ?? 'USD');
        $amountUnits = $montoEsperado / 100;
        $referenceId = sprintf('cita:%s:tipo:%s', $cita->uuid, $tipoSolicitado);

        try {
            $result = $paypal->crearOrden(
                referenceId: $referenceId,
                amount:      $amountUnits,
                currency:    $moneda,
                description: sprintf('TarotEstrellas - %s %s',
                    $tipoSolicitado === 'abono_20' ? 'Abono 20%' : 'Saldo 80%',
                    $cita->codigo_referencia),
                returnUrl: $frontendUrl . '/pago-resultado?cita=' . $cita->uuid . '&canal=paypal',
                cancelUrl: $frontendUrl . '/pagar-cita/' . $cita->uuid . '?cancelado=1',
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Error al crear orden PayPal: ' . $e->getMessage()], 502);
        }

        $pago = $cita->pagos()->create([
            'uuid'               => (string) Str::uuid(),
            'tipo'               => $tipoSolicitado,
            'canal'              => 'paypal',
            'monto_centavos'     => $montoEsperado,
            'moneda'             => $moneda,
            'estado'             => 'pendiente',
            'referencia_externa' => 'paypal:' . $result['order_id'],
            'metadata'           => [
                'paypal_order_id' => $result['order_id'],
                'reference_id'    => $referenceId,
            ],
        ]);

        return response()->json([
            'message' => 'Orden PayPal creada correctamente.',
            'data'    => [
                'cita_uuid'      => $cita->uuid,
                'pago_uuid'      => $pago->uuid,
                'tipo'           => $tipoSolicitado,
                'monto_centavos' => $montoEsperado,
                'moneda'         => $moneda,
                'approval_url'   => $result['approval_url'],
                'order_id'       => $result['order_id'],
            ],
        ], 201);
    }

    public function confirmarPagoPaypal(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'order_id' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $cita = Cita::query()
            ->where('uuid', $uuid)
            ->where('cliente_id', $user->id)
            ->firstOrFail();

        $orderId = (string) $request->input('order_id');

        $existingPago = $cita->pagos()
            ->where('referencia_externa', 'paypal:' . $orderId)
            ->where('estado', 'completado')
            ->first();

        if ($existingPago) {
            return response()->json([
                'message' => 'Pago ya procesado.',
                'data'    => ['pago_uuid' => $existingPago->uuid, 'estado' => 'completado'],
            ]);
        }

        $paypal = app(\App\Services\PaypalService::class);

        try {
            $capture = $paypal->capturarOrden($orderId);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Error al capturar orden PayPal: ' . $e->getMessage()], 502);
        }

        $captureStatus = (string) ($capture['status'] ?? '');

        if ($captureStatus !== 'COMPLETED') {
            return response()->json([
                'message' => 'PayPal orden no completada. Estado: ' . $captureStatus,
            ], 422);
        }

        $pago = $cita->pagos()
            ->where('referencia_externa', 'paypal:' . $orderId)
            ->where('estado', 'pendiente')
            ->first();

        $tipoPago  = $cita->estado === 'pendiente_abono' ? 'abono_20' : 'saldo_80';
        $amount    = (float) data_get($capture, 'purchase_units.0.payments.captures.0.amount.value', 0);
        $currency  = strtoupper((string) data_get($capture, 'purchase_units.0.payments.captures.0.amount.currency_code', $cita->moneda));
        $captureId = (string) data_get($capture, 'purchase_units.0.payments.captures.0.id', '');

        if ($pago) {
            $pago->forceFill([
                'estado'    => 'completado',
                'pagado_en' => now(),
                'metadata'  => array_merge($pago->metadata ?? [], [
                    'paypal_capture_id' => $captureId,
                    'paypal_status'     => $captureStatus,
                ]),
            ])->save();
        } else {
            $pago = $cita->pagos()->create([
                'uuid'               => (string) Str::uuid(),
                'tipo'               => $tipoPago,
                'canal'              => 'paypal',
                'monto_centavos'     => (int) round($amount * 100),
                'moneda'             => $currency,
                'estado'             => 'completado',
                'referencia_externa' => 'paypal:' . $orderId,
                'pagado_en'          => now(),
                'metadata'           => [
                    'paypal_order_id'   => $orderId,
                    'paypal_capture_id' => $captureId,
                ],
            ]);
            $tipoPago = $pago->tipo;
        }

        if ($tipoPago === 'abono_20' && $cita->estado === 'pendiente_abono') {
            $cita->forceFill(['estado' => 'reservada'])->save();
            $cita->load(['cliente:id,email', 'cliente.profile:user_id,nombre', 'tipoConsulta:id,nombre,duracion_minutos']);
            if ($cita->cliente?->email) {
                Mail::to($cita->cliente->email)->send(new \App\Mail\CitaReservadaMail($cita));
            }
        } elseif ($tipoPago === 'saldo_80' && $cita->estado === 'reservada') {
            $cita->forceFill(['estado' => 'confirmada', 'confirmada_en' => now()])->save();
            $cita->load(['cliente:id,email', 'cliente.profile:user_id,nombre', 'tipoConsulta:id,nombre,duracion_minutos']);
            if ($cita->cliente?->email) {
                Mail::to($cita->cliente->email)->send(new \App\Mail\CitaConfirmadaMail($cita));
            }
        }

        return response()->json([
            'message' => 'Pago PayPal confirmado.',
            'data'    => [
                'pago_uuid'      => $pago->uuid,
                'estado'         => 'completado',
                'monto_centavos' => $pago->monto_centavos,
                'moneda'         => $pago->moneda,
            ],
        ]);
    }

    public function datosTransferencia(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $cita = Cita::query()
            ->where('uuid', $uuid)
            ->where('cliente_id', $user->id)
            ->firstOrFail();

        if ($cita->canal_pago !== 'transferencia') {
            return response()->json([
                'message' => 'La cita no utiliza pago por transferencia.',
            ], 422);
        }

        // Leer cuentas bancarias configuradas (del especialista asignado, o las globales)
        $cuentasQuery = \App\Models\CuentaBancaria::query()
            ->where('activa', true)
            ->orderBy('orden');

        if ($cita->especialista_id) {
            $cuentasQuery->where('user_id', $cita->especialista_id);
        }

        $cuentas = $cuentasQuery->get(['banco', 'tipo_cuenta', 'numero_cuenta', 'nombre_titular', 'rut_titular', 'orden']);

        // Fallback a AppSettings si no hay cuentas configuradas
        if ($cuentas->isEmpty()) {
            $banco      = (string) AppSetting::getValue(self::TRANSFERENCIA_BANCO_KEY, '');
            $titular    = (string) AppSetting::getValue(self::TRANSFERENCIA_TITULAR_KEY, '');
            $cuenta     = (string) AppSetting::getValue(self::TRANSFERENCIA_CUENTA_KEY, '');
            $tipoCuenta = (string) AppSetting::getValue(self::TRANSFERENCIA_TIPO_CUENTA_KEY, 'Corriente');
            $rut        = (string) AppSetting::getValue(self::TRANSFERENCIA_RUT_KEY, '');

            if ($banco || $cuenta) {
                $cuentas = collect([[
                    'banco'          => $banco,
                    'tipo_cuenta'    => $tipoCuenta,
                    'numero_cuenta'  => $cuenta,
                    'nombre_titular' => $titular,
                    'rut_titular'    => $rut,
                    'orden'          => 0,
                ]]);
            }
        }

        $montoMinimo = (int) round(((int) $cita->precio_final_centavos) * 0.20);

        return response()->json([
            'data' => [
                'cita_uuid'                     => $cita->uuid,
                'codigo_referencia'             => $cita->codigo_referencia,
                'monto_minimo_abono_centavos'   => $montoMinimo,
                'moneda'                        => $cita->moneda,
                'minutos_ventana_transferencia' => $this->getPositiveIntSetting(self::MINUTOS_VENTANA_TRANSFERENCIA_KEY, 30),
                'cuentas_bancarias'             => $cuentas->values(),
            ],
        ]);
    }

    public function reagendar(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'inicio_utc' => ['nullable', 'date'],
            'inicio_local' => ['required_without:inicio_utc', 'date_format:Y-m-d H:i:s'],
            'zona_horaria_cliente' => ['required', 'timezone'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $cita = Cita::query()
            ->with('tipoConsulta:id,slug,nombre,duracion_minutos')
            ->where('uuid', $uuid)
            ->where('cliente_id', $user->id)
            ->firstOrFail();

        if (! in_array($cita->estado, ['pendiente_abono', 'reservada', 'confirmada'], true)) {
            return response()->json([
                'message' => 'La cita no se puede reagendar en su estado actual.',
            ], 422);
        }

        if (! $cita->inicio_utc || $cita->inicio_utc->diffInHours(now(), false) > -24) {
            return response()->json([
                'message' => 'Faltan menos de 24 horas para la cita. No es posible reagendar.',
            ], 422);
        }

        $startUtc = ! empty($validated['inicio_utc'])
            ? CarbonImmutable::parse($validated['inicio_utc'])->setTimezone('UTC')
            : CarbonImmutable::parse($validated['inicio_local'], $validated['zona_horaria_cliente'])->setTimezone('UTC');
        $endUtc = $startUtc->addMinutes((int) $cita->duracion_minutos);

        if ($startUtc->lt(CarbonImmutable::now('UTC')->addMinutes(self::MIN_BOOKING_LEAD_MINUTES))) {
            return response()->json([
                'message' => 'Debes reagendar con al menos 1 hora de anticipación.',
            ], 422);
        }

        $collision = Cita::query()
            ->where('id', '!=', $cita->id)
            ->whereIn('estado', ['pendiente_abono', 'reservada', 'confirmada', 'en_curso'])
            ->where('inicio_utc', '<', $endUtc->addMinutes(self::SERVICE_BUFFER_MINUTES)->toDateTimeString())
            ->where('fin_utc', '>', $startUtc->subMinutes(self::SERVICE_BUFFER_MINUTES)->toDateTimeString())
            ->exists();

        if ($collision) {
            return response()->json([
                'message' => 'El nuevo horario seleccionado no se encuentra disponible.',
            ], 422);
        }

        $reagendamientoGratuito = ! Reagendamiento::query()
            ->where('reagendado_por', $user->id)
            ->exists();

        $nuevaCita = DB::transaction(function () use ($cita, $startUtc, $endUtc, $validated, $user, $reagendamientoGratuito) {
            $nuevaCita = Cita::query()->create([
                'uuid' => (string) Str::uuid(),
                'codigo_referencia' => $this->generateReferenceCode(),
                'cliente_id' => $cita->cliente_id,
                'especialista_id' => $cita->especialista_id,
                'tipo_consulta_id' => $cita->tipo_consulta_id,
                'inicio_utc' => $startUtc->toDateTimeString(),
                'fin_utc' => $endUtc->toDateTimeString(),
                'duracion_minutos' => $cita->duracion_minutos,
                'zona_horaria_cliente' => $validated['zona_horaria_cliente'],
                'estado' => $cita->estado,
                'canal_pago' => $cita->canal_pago,
                'precio_total_centavos' => $cita->precio_total_centavos,
                'precio_final_centavos' => $cita->precio_final_centavos,
                'moneda' => $cita->moneda,
                'tema_principal' => $cita->tema_principal,
                'notas_cliente' => $cita->notas_cliente,
                'notas_chachita' => $cita->notas_chachita,
                'reservada_hasta' => $cita->estado === 'pendiente_abono'
                    ? now()->addMinutes($this->getPositiveIntSetting(self::MINUTOS_VENTANA_TRANSFERENCIA_KEY, 30))
                    : $cita->reservada_hasta,
                'extension_reserva_aplicada_en' => null,
                'extension_reserva_conteo' => 0,
                'es_primera_consulta' => $cita->es_primera_consulta,
            ]);

            Reagendamiento::query()->create([
                'cita_original_id' => $cita->id,
                'cita_nueva_id' => $nuevaCita->id,
                'motivo' => $validated['motivo'] ?? null,
                'gratuito' => $reagendamientoGratuito,
                'reagendado_por' => $user->id,
            ]);

            $cita->forceFill([
                'estado' => 'reagendada',
                'cancelada_en' => now(),
                'motivo_cancelacion' => $validated['motivo'] ?? 'Reagendada por cliente',
            ])->save();

            return $nuevaCita;
        });

        return response()->json([
            'message' => 'Cita reagendada correctamente.',
            'data' => [
                'cita_original_uuid' => $cita->uuid,
                'cita_nueva' => $nuevaCita->fresh(['tipoConsulta:id,slug,nombre,duracion_minutos']),
                'gratuito' => $reagendamientoGratuito,
            ],
        ]);
    }

    public function cancelar(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $cita = Cita::query()
            ->where('uuid', $uuid)
            ->where('cliente_id', $user->id)
            ->firstOrFail();

        if (in_array($cita->estado, ['cancelada_cliente', 'cancelada_especialista', 'finalizada', 'expirada', 'reagendada'], true)) {
            return response()->json([
                'message' => 'La cita no se puede cancelar en su estado actual.',
            ], 422);
        }

        $horasAnticipacionReembolso = $this->getPositiveIntSetting(self::HORAS_REEMBOLSO_ANTICIPACION_KEY, 24);
        $maxCancelacionesConReembolso = max(
            0,
            (int) AppSetting::getValue(self::MAX_CANCELACIONES_CON_REEMBOLSO_CLIENTE_KEY, 1)
        );

        $cancelacionConAnticipacion =
            $cita->inicio_utc !== null
            && $cita->inicio_utc->greaterThanOrEqualTo(now()->addHours($horasAnticipacionReembolso));

        $cancelacionesConReembolsoPrevias = Reembolso::query()
            ->where('cliente_id', $user->id)
            ->where('razon', 'cancelacion_24h')
            ->count();

        $reembolsoAplicaPorPolitica =
            $cancelacionConAnticipacion
            && $maxCancelacionesConReembolso > 0
            && $cancelacionesConReembolsoPrevias < $maxCancelacionesConReembolso;

        $abonoCompletado = Pago::query()
            ->where('cita_id', $cita->id)
            ->where('tipo', 'abono_20')
            ->where('estado', 'completado')
            ->orderByDesc('pagado_en')
            ->first();

        $reembolso = DB::transaction(function () use ($cita, $validated, $user, $reembolsoAplicaPorPolitica, $abonoCompletado) {
            $cita->forceFill([
                'estado' => 'cancelada_cliente',
                'cancelada_en' => now(),
                'motivo_cancelacion' => $validated['motivo'] ?? 'Cancelada por cliente',
            ])->save();

            if (! $reembolsoAplicaPorPolitica || ! $abonoCompletado) {
                return null;
            }

            return Reembolso::query()->create([
                'uuid' => (string) Str::uuid(),
                'cita_id' => $cita->id,
                'cliente_id' => $user->id,
                'pago_id' => $abonoCompletado->id,
                'monto_centavos' => (int) $abonoCompletado->monto_centavos,
                'moneda' => $abonoCompletado->moneda,
                'estado' => 'pendiente',
                'razon' => 'cancelacion_24h',
                'metodo' => 'mismo_medio_pago',
                'solicitado_en' => now(),
                'metadata' => [
                    'cita_uuid' => $cita->uuid,
                    'canal_pago_cita' => $cita->canal_pago,
                ],
            ]);
        });

        $motivoPolitica = 'cancelacion_sin_reembolso';
        if ($reembolso) {
            $motivoPolitica = 'reembolso_24h_aplicado';
        } elseif (! $cancelacionConAnticipacion) {
            $motivoPolitica = 'menos_de_24h_sin_reembolso';
        } elseif ($maxCancelacionesConReembolso <= 0) {
            $motivoPolitica = 'reembolsos_deshabilitados';
        } elseif ($cancelacionesConReembolsoPrevias >= $maxCancelacionesConReembolso) {
            $motivoPolitica = 'limite_reembolsos_alcanzado';
        } elseif (! $abonoCompletado) {
            $motivoPolitica = 'sin_abono_completado';
        }

        if ($cita->cliente?->email) {
            $cita->load(['cliente.profile:user_id,nombre', 'tipoConsulta:id,nombre,duracion_minutos']);
            Mail::to($cita->cliente->email)->send(new CitaCanceladaMail($cita, $reembolso !== null));
        }

        return response()->json([
            'message' => 'Cita cancelada correctamente.',
            'data' => [
                'uuid' => $cita->uuid,
                'estado' => $cita->estado,
                'cancelada_en' => optional($cita->cancelada_en)->toIso8601String(),
                'motivo_cancelacion' => $cita->motivo_cancelacion,
                'reembolso' => [
                    'aplica' => $reembolso !== null,
                    'uuid' => $reembolso?->uuid,
                    'estado' => $reembolso?->estado,
                    'monto_centavos' => $reembolso?->monto_centavos,
                    'moneda' => $reembolso?->moneda,
                    'motivo_politica' => $motivoPolitica,
                ],
            ],
        ]);
    }

    public function confirmarAsistencia(Request $request, string $uuid)
    {
        if (! $request->hasValidSignature()) {
            return response()->view('emails.confirmacion-link-invalido', [], 403);
        }

        $cita = Cita::query()->where('uuid', $uuid)->first();
        if (! $cita) {
            return response()->view('emails.confirmacion-link-invalido', [], 404);
        }

        if (! in_array($cita->estado, ['confirmada', 'pagada', 'reservada'], true)) {
            return response()->view('emails.confirmacion-resultado', [
                'titulo' => 'No pudimos confirmar tu asistencia',
                'mensaje' => 'Esta cita ya no está activa (estado: ' . $cita->estado . ').',
            ]);
        }

        if (! $cita->cliente_confirmo_at) {
            $cita->cliente_confirmo_at = now();
            $cita->save();
        }

        $frontend = config('app.frontend_url') ?: rtrim(config('app.url'), '/');
        return redirect()->away($frontend . '/app/citas/' . $cita->uuid . '?asistencia_confirmada=1');
    }

    public function marcarNoShow(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'cliente_asistio' => ['nullable', 'boolean'],
            'especialista_asistio' => ['nullable', 'boolean'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! $user->isAdmin()) {
            return response()->json([
                'message' => 'No autorizado para marcar no-show.',
            ], 403);
        }

        $cita = Cita::query()
            ->with('pagos')
            ->where('uuid', $uuid)
            ->firstOrFail();

        if (! in_array($cita->estado, ['confirmada', 'en_curso'], true)) {
            return response()->json([
                'message' => 'La cita no se puede marcar no-show en su estado actual.',
            ], 422);
        }

        $clienteAsistio = (bool) ($validated['cliente_asistio'] ?? false);
        $especialistaAsistio = (bool) ($validated['especialista_asistio'] ?? true);

        if ($clienteAsistio) {
            return response()->json([
                'message' => 'No corresponde marcar no-show si el cliente asistio.',
            ], 422);
        }

        $reembolsos = DB::transaction(function () use ($cita, $validated, $user, $especialistaAsistio) {
            if ($especialistaAsistio) {
                $cita->forceFill([
                    'estado' => 'no_show',
                    'cancelada_en' => now(),
                    'motivo_cancelacion' => $validated['motivo'] ?? 'Cliente no se presento a la sesion',
                ])->save();

                return collect();
            }

            $cita->forceFill([
                'estado' => 'cancelada_chachita',
                'cancelada_en' => now(),
                'motivo_cancelacion' => $validated['motivo'] ?? 'Especialista no asistio a la sesion',
            ])->save();

            $pagosCompletados = $cita->pagos()
                ->where('estado', 'completado')
                ->get();

            return $pagosCompletados->map(function (Pago $pago) use ($cita) {
                return Reembolso::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'cita_id' => $cita->id,
                    'cliente_id' => $cita->cliente_id,
                    'pago_id' => $pago->id,
                    'monto_centavos' => (int) $pago->monto_centavos,
                    'moneda' => $pago->moneda,
                    'estado' => 'pendiente',
                    'razon' => 'cancelacion_chachita',
                    'metodo' => 'mismo_medio_pago',
                    'solicitado_en' => now(),
                    'metadata' => [
                        'cita_uuid' => $cita->uuid,
                        'origen' => 'admin_no_show',
                    ],
                ]);
            });
        });

        return response()->json([
            'message' => 'No-show procesado correctamente.',
            'data' => [
                'uuid' => $cita->uuid,
                'estado' => $cita->fresh()->estado,
                'motivo_cancelacion' => $cita->fresh()->motivo_cancelacion,
                'reembolsos_generados' => $reembolsos->count(),
                'monto_reembolso_total_centavos' => $reembolsos->sum('monto_centavos'),
            ],
        ]);
    }

    private function getPositiveIntSetting(string $key, int $default): int
    {
        return max(1, (int) AppSetting::getValue($key, $default));
    }

    private function generateReferenceCode(): string
    {
        do {
            $code = sprintf(
                'TE-%s-%s',
                strtoupper(Str::random(4)),
                strtoupper(Str::random(4))
            );
        } while (Cita::query()->where('codigo_referencia', $code)->exists());

        return $code;
    }

    private function extractBasicComprobanteData(UploadedFile $file, string $storedPath): array
    {
        $baseData = [
            'banco_origen' => null,
            'banco_destino' => null,
            'cuenta_destino' => null,
            'rut_titular_destino' => null,
            'monto_clp' => null,
            'fecha_transferencia' => null,
            'hora_transferencia' => null,
            'id_transaccion' => null,
            'mensaje_glosa' => null,
            'tipo_transferencia' => null,
        ];

        $sourceText = $file->getClientOriginalName();
        $mime = strtolower($file->getClientMimeType() ?: '');

        if (str_contains($mime, 'text/plain') && Storage::disk('local')->exists($storedPath)) {
            $text = (string) Storage::disk('local')->get($storedPath);
            $sourceText .= "\n".$text;
        }

        if (preg_match('/\b(TX-[A-Z0-9]+(?:-[A-Z0-9]+)*)\b/i', $sourceText, $m)) {
            $baseData['id_transaccion'] = strtoupper($m[1]);
        }

        if (preg_match('/\b(\d{6,12})\b/', $sourceText, $m)) {
            $baseData['cuenta_destino'] = $m[1];
        }

        if (preg_match('/\b(\d{1,3}(?:[\.,]\d{3})+)\b/', $sourceText, $m)) {
            $baseData['monto_clp'] = (int) preg_replace('/\D+/', '', $m[1]);
        }

        if (preg_match('/\b(\d{2}[\/-]\d{2}[\/-]\d{4})\b/', $sourceText, $m)) {
            $baseData['fecha_transferencia'] = str_replace('/', '-', $m[1]);
        }

        if (preg_match('/\b(\d{2}:\d{2})(?::\d{2})?\b/', $sourceText, $m)) {
            $baseData['hora_transferencia'] = $m[1];
        }

        if (preg_match('/\b(\d{7,8}-[\dkK])\b/u', $sourceText, $m)) {
            $baseData['rut_titular_destino'] = strtoupper($m[1]);
        }

        return $baseData;
    }

    private function escapeIcsText(string $text): string
    {
        return str_replace(
            ["\\", ';', ',', "\r\n", "\r", "\n"],
            ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'],
            $text
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function validateHistorialAdminFilters(Request $request): array
    {
        return $request->validate([
            'estado' => ['nullable', 'string', 'max:50'],
            'cliente_email' => ['nullable', 'email'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'q' => ['nullable', 'string', 'max:100'],
            'sort_by' => ['nullable', 'string', 'in:inicio_utc,estado,codigo_referencia'],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],
        ]);
    }

    /**
     * @param array<string,mixed> $validated
     */
    private function buildHistorialAdminQuery(array $validated)
    {
        $query = Cita::query()
            ->with([
                'cliente:id,name,email',
                'tipoConsulta:id,slug,nombre,duracion_minutos',
                'grabaciones.transcripcion.resumen',
            ]);

        // Búsqueda general por UUID, código, email cliente
        if (! empty($validated['q'])) {
            $search = $validated['q'];
            $query->where(function ($q) use ($search) {
                $q->where('uuid', 'like', '%' . $search . '%')
                  ->orWhere('codigo_referencia', 'like', '%' . $search . '%')
                  ->orWhereHas('cliente', function ($sq) use ($search) {
                      $sq->where('email', 'like', '%' . $search . '%')
                         ->orWhere('name', 'like', '%' . $search . '%');
                  });
            });
        }

        if (! empty($validated['estado'])) {
            $query->where('estado', $validated['estado']);
        }

        if (! empty($validated['cliente_email'])) {
            $email = $validated['cliente_email'];
            $query->whereHas('cliente', function ($q) use ($email) {
                $q->where('email', $email);
            });
        }

        if (! empty($validated['desde'])) {
            $query->whereDate('inicio_utc', '>=', $validated['desde']);
        }

        if (! empty($validated['hasta'])) {
            $query->whereDate('inicio_utc', '<=', $validated['hasta']);
        }

        // Ordenamiento
        $sortBy = $validated['sort_by'] ?? 'inicio_utc';
        $sortDir = $validated['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query;
    }
}
