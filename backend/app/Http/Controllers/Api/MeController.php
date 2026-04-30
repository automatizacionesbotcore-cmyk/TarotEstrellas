<?php

namespace App\Http\Controllers\Api;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Grabacion;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function consultas(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $citas = $this->buildConsultasQuery($user)->get();

        return response()->json([
            'data' => $citas,
        ]);
    }

    public function consultaDetalle(Request $request, string $uuid): JsonResponse
    {
        $cita = $this->findUserCita($request, $uuid);

        if (! $cita) {
            return response()->json([
                'message' => 'Consulta no encontrada.',
            ], 404);
        }

        return response()->json([
            'data' => $cita,
        ]);
    }

    public function export(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->load([
            'profile',
            'datoNatal',
            'preferenciaNotificacion',
            'consentimientos',
            'roles:id,nombre',
        ]);

        $citas = $this->buildConsultasQuery($user)->get();

        return response()->json([
            'exportado_en' => now()->toIso8601String(),
            'data' => [
                'usuario' => [
                    'id' => $user->id,
                    'uuid' => $user->uuid,
                    'email' => $user->email,
                    'name' => $user->name,
                    'roles' => $user->roles->pluck('nombre')->values(),
                    'created_at' => optional($user->created_at)->toIso8601String(),
                    'updated_at' => optional($user->updated_at)->toIso8601String(),
                ],
                'perfil' => $user->profile,
                'dato_natal' => $user->datoNatal,
                'preferencias_notificacion' => $user->preferenciaNotificacion,
                'consentimientos' => $user->consentimientos
                    ->sortByDesc('otorgado_en')
                    ->values(),
                'citas' => $citas->map(function (Cita $cita) {
                    return [
                        'uuid' => $cita->uuid,
                        'codigo_referencia' => $cita->codigo_referencia,
                        'estado' => $cita->estado,
                        'canal_pago' => $cita->canal_pago,
                        'precio_total_centavos' => $cita->precio_total_centavos,
                        'precio_final_centavos' => $cita->precio_final_centavos,
                        'moneda' => $cita->moneda,
                        'inicio_utc' => optional($cita->inicio_utc)->toIso8601String(),
                        'fin_utc' => optional($cita->fin_utc)->toIso8601String(),
                        'tipo_consulta' => $cita->tipoConsulta,
                        'pagos' => $cita->pagos,
                        'historial' => $cita->grabaciones->map(function ($grabacion) {
                            return [
                                'grabacion' => $grabacion,
                                'transcripcion' => $grabacion->transcripcion,
                                'resumen' => $grabacion->transcripcion ? $grabacion->transcripcion->resumen : null,
                            ];
                        })->values(),
                    ];
                })->values(),
            ],
        ]);
    }

    public function datosPersonales(Request $request): JsonResponse
    {
        return $this->export($request);
    }

    public function exportConsultasPdf(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $citas = $this->buildConsultasQuery($user)->get();

        $html = view('exports.consultas-pdf', [
            'user' => $user,
            'citas' => $citas,
            'exportadoEn' => now(),
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4');

        $filename = 'consultas-'.$user->uuid.'-'.now()->format('Ymd-His').'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function consultaTranscripcion(Request $request, string $uuid): JsonResponse
    {
        // Permisos: el cliente NO accede al texto crudo. Se le redirige al resumen.
        return response()->json([
            'message' => 'La transcripcion completa solo esta disponible para administradores. Consulta el resumen de tu sesion.',
        ], 403);
    }

    public function consultaResumen(Request $request, string $uuid): JsonResponse
    {
        $cita = $this->findUserCita($request, $uuid);
        if (! $cita) {
            return response()->json([
                'message' => 'Consulta no encontrada.',
            ], 404);
        }

        $grabacion = $this->latestGrabacion($cita);

        if (! $grabacion || ! $grabacion->transcripcion || ! $grabacion->transcripcion->resumen) {
            return response()->json([
                'message' => 'Resumen no disponible para esta consulta.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'cita_uuid' => $cita->uuid,
                'grabacion_id' => $grabacion->id,
                'resumen' => $grabacion->transcripcion->resumen,
            ],
        ]);
    }

    public function consultaGrabacionUrl(Request $request, string $uuid): JsonResponse
    {
        $cita = $this->findUserCita($request, $uuid);
        if (! $cita) {
            return response()->json([
                'message' => 'Consulta no encontrada.',
            ], 404);
        }

        $grabacion = $this->latestGrabacion($cita);

        if (! $grabacion || ! $grabacion->url_grabacion) {
            return response()->json([
                'message' => 'Grabacion no disponible para esta consulta.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'cita_uuid' => $cita->uuid,
                'grabacion_id' => $grabacion->id,
                'url' => $grabacion->url_grabacion,
                'expires_at' => now()->addMinutes(15)->toIso8601String(),
            ],
        ]);
    }

    public function actualizarNotasPrivadas(Request $request, string $uuid): JsonResponse
    {
        $cita = $this->findUserCita($request, $uuid);
        if (! $cita) {
            return response()->json([
                'message' => 'Consulta no encontrada.',
            ], 404);
        }

        $validated = $request->validate([
            'notas_privadas' => ['required', 'string', 'max:5000'],
        ]);

        $cita->forceFill([
            'notas_cliente' => $validated['notas_privadas'],
        ])->save();

        return response()->json([
            'message' => 'Notas privadas actualizadas.',
            'data' => [
                'cita_uuid' => $cita->uuid,
                'notas_privadas' => $cita->notas_cliente,
            ],
        ]);
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'confirmacion' => ['required', 'in:ELIMINAR'],
        ]);

        /** @var User $user */
        $user = $request->user();

        // Revocar todos los tokens antes de eliminar
        $user->tokens()->delete();

        // Soft delete — el registro persiste con deleted_at para reactivación futura
        $user->delete();

        return response()->json(['message' => 'Cuenta eliminada correctamente.']);
    }

    private function buildConsultasQuery(User $user)
    {
        return Cita::query()
            ->where('cliente_id', $user->id)
            ->with([
                'tipoConsulta:id,slug,nombre,duracion_minutos',
                'pagos:id,cita_id,uuid,tipo,canal,monto_centavos,moneda,estado,pagado_en,created_at',
                'grabaciones:id,cita_id,daily_recording_id,url_grabacion,estado,created_at',
                'grabaciones.transcripcion:id,cita_id,grabacion_id,contenido,proveedor,modelo,idioma,created_at',
                'grabaciones.transcripcion.resumen:id,cita_id,grabacion_id,transcripcion_id,contenido,proveedor,modelo,created_at',
            ])
            ->orderByDesc('inicio_utc');
    }

    private function findUserCita(Request $request, string $uuid): ?Cita
    {
        /** @var User $user */
        $user = $request->user();

        return Cita::query()
            ->where('uuid', $uuid)
            ->where('cliente_id', $user->id)
            ->with([
                'grabaciones.transcripcion.resumen',
            ])
            ->first();
    }

    private function latestGrabacion(Cita $cita): ?Grabacion
    {
        return $cita->grabaciones
            ->sortByDesc('id')
            ->first();
    }
}
