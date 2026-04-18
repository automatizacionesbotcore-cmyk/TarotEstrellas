<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ValidarComprobanteJob;
use App\Models\Cita;
use App\Models\ComprobanteTransferencia;
use App\Models\AppSetting;
use App\Models\TipoConsulta;
use App\Models\User;
use App\Models\ValidacionAgente;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CitaController extends Controller
{
    private const MINUTOS_VENTANA_TRANSFERENCIA_KEY = 'MINUTOS_VENTANA_TRANSFERENCIA';
    private const MINUTOS_EXTENSION_TRANSFERENCIA_KEY = 'MINUTOS_EXTENSION_TRANSFERENCIA';
    private const EXTENSIONES_PERMITIDAS_KEY = 'EXTENSIONES_PERMITIDAS';

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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipo_consulta_slug' => ['required', 'string', 'exists:tipos_consulta,slug'],
            'inicio_local' => ['required', 'date_format:Y-m-d H:i:s'],
            'zona_horaria_cliente' => ['required', 'timezone'],
            'canal_pago' => ['required', 'in:stripe,transferencia'],
            'moneda' => ['nullable', 'string', 'size:3'],
            'tema_principal' => ['nullable', 'string', 'max:50'],
            'notas_cliente' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $tipo = TipoConsulta::query()
            ->where('slug', $validated['tipo_consulta_slug'])
            ->where('activo', true)
            ->firstOrFail();

        $startUtc = CarbonImmutable::parse($validated['inicio_local'], $validated['zona_horaria_cliente'])
            ->setTimezone('UTC');
        $endUtc = $startUtc->addMinutes((int) $tipo->duracion_minutos);

        $collision = Cita::query()
            ->whereIn('estado', ['pendiente_abono', 'reservada', 'confirmada', 'en_curso'])
            ->where('inicio_utc', '<', $endUtc)
            ->where('fin_utc', '>', $startUtc)
            ->exists();

        if ($collision) {
            throw ValidationException::withMessages([
                'inicio_local' => 'El horario seleccionado ya no se encuentra disponible.',
            ]);
        }

        $price = (int) $tipo->precio_referencial_centavos;
        $isPrimeraConsulta = ! Cita::query()->where('cliente_id', $user->id)->exists();

        $cita = Cita::query()->create([
            'uuid' => (string) Str::uuid(),
            'codigo_referencia' => $this->generateReferenceCode(),
            'cliente_id' => $user->id,
            'especialista_id' => null,
            'tipo_consulta_id' => $tipo->id,
            'inicio_utc' => $startUtc,
            'fin_utc' => $endUtc,
            'duracion_minutos' => (int) $tipo->duracion_minutos,
            'zona_horaria_cliente' => $validated['zona_horaria_cliente'],
            'estado' => 'pendiente_abono',
            'canal_pago' => $validated['canal_pago'],
            'precio_total_centavos' => $price,
            'precio_final_centavos' => $price,
            'moneda' => strtoupper($validated['moneda'] ?? $tipo->moneda ?? 'CLP'),
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

    public function crearPaymentIntentStripe(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'tipo' => ['nullable', 'in:abono_20,saldo_80'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $cita = Cita::query()
            ->where('uuid', $uuid)
            ->where('cliente_id', $user->id)
            ->firstOrFail();

        if ($cita->canal_pago !== 'stripe') {
            throw ValidationException::withMessages([
                'uuid' => 'La cita no corresponde a flujo de pago con Stripe.',
            ]);
        }

        $tipoEsperado = null;
        $montoEsperado = 0;

        $abonoObjetivo = (int) round($cita->precio_final_centavos * 0.20);
        $saldoObjetivo = max(0, (int) $cita->precio_final_centavos - $abonoObjetivo);

        if ($cita->estado === 'pendiente_abono') {
            $tipoEsperado = 'abono_20';
            $montoEsperado = $abonoObjetivo;
        } elseif ($cita->estado === 'reservada') {
            $tipoEsperado = 'saldo_80';
            $montoEsperado = $saldoObjetivo;
        }

        if (! $tipoEsperado || $montoEsperado <= 0) {
            throw ValidationException::withMessages([
                'uuid' => 'La cita no esta en estado valido para crear PaymentIntent.',
            ]);
        }

        $tipoSolicitado = $validated['tipo'] ?? $tipoEsperado;
        if ($tipoSolicitado !== $tipoEsperado) {
            throw ValidationException::withMessages([
                'tipo' => 'El tipo de pago no coincide con el estado actual de la cita.',
            ]);
        }

        $secretKey = (string) config('services.stripe.secret', '');
        if ($secretKey === '') {
            return response()->json([
                'message' => 'Stripe no esta configurado.',
            ], 503);
        }

        $payload = [
            'amount' => $montoEsperado,
            'currency' => strtolower($cita->moneda),
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => [
                'cita_uuid' => $cita->uuid,
                'tipo_pago' => $tipoSolicitado,
            ],
            'description' => sprintf('TarotEstrellas %s %s', $tipoSolicitado, $cita->codigo_referencia),
        ];

        $response = Http::asForm()
            ->withToken($secretKey)
            ->withHeaders([
                'Idempotency-Key' => sprintf('cita:%s:tipo:%s', $cita->uuid, $tipoSolicitado),
            ])
            ->post('https://api.stripe.com/v1/payment_intents', $payload);

        if ($response->failed()) {
            return response()->json([
                'message' => (string) data_get($response->json(), 'error.message', 'No fue posible crear PaymentIntent en Stripe.'),
            ], 502);
        }

        $intentId = (string) data_get($response->json(), 'id', '');
        $clientSecret = (string) data_get($response->json(), 'client_secret', '');
        $intentStatus = (string) data_get($response->json(), 'status', 'requires_payment_method');

        if ($intentId === '' || $clientSecret === '') {
            return response()->json([
                'message' => 'Stripe respondio sin identificador de PaymentIntent.',
            ], 502);
        }

        $pago = $cita->pagos()->create([
            'uuid' => (string) Str::uuid(),
            'tipo' => $tipoSolicitado,
            'canal' => 'stripe',
            'monto_centavos' => $montoEsperado,
            'moneda' => $cita->moneda,
            'estado' => 'pendiente',
            'stripe_payment_intent_id' => $intentId,
            'referencia_externa' => $intentId,
            'metadata' => [
                'stripe_status' => $intentStatus,
            ],
        ]);

        return response()->json([
            'message' => 'PaymentIntent creado correctamente.',
            'data' => [
                'cita_uuid' => $cita->uuid,
                'pago_uuid' => $pago->uuid,
                'tipo' => $tipoSolicitado,
                'monto_centavos' => $montoEsperado,
                'moneda' => $cita->moneda,
                'payment_intent_id' => $intentId,
                'client_secret' => $clientSecret,
                'status' => $intentStatus,
            ],
        ], 201);
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
}
