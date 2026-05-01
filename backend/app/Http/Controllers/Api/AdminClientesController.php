<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Pago;
use App\Models\User;
use App\Services\BriefingIAService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdminClientesController extends Controller
{
    /**
     * GET /api/admin/clientes
     * Lista paginada de clientes con filtros.
     * Query: q, pais, signo, frecuencia (sin_consultas|baja|media|alta), activos (1|0), desde, hasta, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['sometimes', 'string', 'max:200'],
            'pais' => ['sometimes', 'string', 'size:2'],
            'signo' => ['sometimes', 'string', 'max:30'],
            'frecuencia' => ['sometimes', 'in:sin_consultas,baja,media,alta'],
            'activos' => ['sometimes', 'in:0,1'],
            'membresia_activa' => ['sometimes', 'in:0,1'],
            'inactivo_meses' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'desde' => ['sometimes', 'date'],
            'hasta' => ['sometimes', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $clienteRoleId = DB::table('roles')->where('nombre', 'cliente')->value('id');

        $query = User::query()
            ->select(['users.id', 'users.uuid', 'users.name', 'users.email', 'users.created_at', 'users.last_login_at'])
            ->whereExists(function ($q) use ($clienteRoleId) {
                $q->select(DB::raw(1))->from('user_roles')
                  ->whereColumn('user_roles.user_id', 'users.id')
                  ->where('user_roles.role_id', $clienteRoleId);
            })
            ->with(['profile:id,user_id,nombre,apellido,telefono_pais,pais_residencia,fecha_nacimiento_publica,avatar_url'])
            ->withCount(['datoNatal as tiene_datos_natales']);

        if ($q = trim((string) $request->string('q'))) {
            $query->where(function ($w) use ($q) {
                $w->where('users.name', 'like', "%{$q}%")
                  ->orWhere('users.email', 'like', "%{$q}%")
                  ->orWhereHas('profile', function ($p) use ($q) {
                      $p->where('nombre', 'like', "%{$q}%")
                        ->orWhere('apellido', 'like', "%{$q}%")
                        ->orWhere('telefono', 'like', "%{$q}%");
                  });
            });
        }

        if ($pais = $request->string('pais')->toString()) {
            $query->whereHas('profile', fn ($p) => $p->where('pais_residencia', $pais));
        }

        if ($desde = $request->date('desde')) {
            $query->where('users.created_at', '>=', $desde);
        }
        if ($hasta = $request->date('hasta')) {
            $query->where('users.created_at', '<=', $hasta->endOfDay());
        }

        if ($request->has('activos')) {
            if ($request->boolean('activos')) {
                $query->where('users.last_login_at', '>=', now()->subDays(60));
            } else {
                $query->where(function ($w) {
                    $w->whereNull('users.last_login_at')
                      ->orWhere('users.last_login_at', '<', now()->subDays(60));
                });
            }
        }

        if ($request->has('membresia_activa')) {
            if ($request->boolean('membresia_activa')) {
                $query->whereExists(function ($q) {
                    $q->select(DB::raw(1))->from('membresias')
                      ->whereColumn('membresias.cliente_id', 'users.id')
                      ->where('membresias.estado', 'activa');
                });
            } else {
                $query->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))->from('membresias')
                      ->whereColumn('membresias.cliente_id', 'users.id')
                      ->where('membresias.estado', 'activa');
                });
            }
        }

        if ($meses = (int) $request->integer('inactivo_meses')) {
            $cutoff = now()->subMonths($meses);
            $query->whereNotExists(function ($q) use ($cutoff) {
                $q->select(DB::raw(1))->from('citas')
                  ->whereColumn('citas.cliente_id', 'users.id')
                  ->where('citas.inicio_utc', '>=', $cutoff)
                  ->whereIn('citas.estado', ['confirmada', 'finalizada', 'completada', 'en_curso']);
            });
        }

        $rows = $query->orderByDesc('users.created_at')
            ->paginate((int) $request->integer('per_page', 20));

        $clienteIds = $rows->getCollection()->pluck('id')->all();
        $stats = $this->statsBatch($clienteIds);

        if ($freq = $request->string('frecuencia')->toString()) {
            $rows->setCollection($rows->getCollection()->filter(function ($u) use ($stats, $freq) {
                $total = (int) ($stats[$u->id]['total_completadas'] ?? 0);
                return match ($freq) {
                    'sin_consultas' => $total === 0,
                    'baja' => $total >= 1 && $total <= 2,
                    'media' => $total >= 3 && $total <= 9,
                    'alta' => $total >= 10,
                };
            })->values());
        }

        return response()->json([
            'data' => $rows->getCollection()->map(function ($u) use ($stats) {
                return [
                    'uuid' => $u->uuid,
                    'name' => $u->name,
                    'email' => $u->email,
                    'created_at' => $u->created_at?->toIso8601String(),
                    'last_login_at' => $u->last_login_at?->toIso8601String(),
                    'profile' => $u->profile ? [
                        'nombre' => $u->profile->nombre,
                        'apellido' => $u->profile->apellido,
                        'pais_residencia' => $u->profile->pais_residencia,
                        'avatar_url' => $u->profile->avatar_url,
                        'fecha_nacimiento' => $u->profile->fecha_nacimiento_publica?->toDateString(),
                    ] : null,
                    'tiene_datos_natales' => (bool) $u->tiene_datos_natales,
                    'stats' => $stats[$u->id] ?? $this->emptyStats(),
                ];
            })->all(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    /**
     * GET /api/admin/clientes/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $cliente = User::query()
            ->where('uuid', $uuid)
            ->with([
                'profile',
                'datoNatal',
                'preferenciaNotificacion',
                'consentimientos:id,user_id,tipo,version,otorgado_en,revocado_en',
                'roles:id,nombre',
            ])
            ->firstOrFail();

        $stats = $this->statsForCliente($cliente->id);

        $citas = Cita::query()
            ->where('cliente_id', $cliente->id)
            ->with(['tipoConsulta:id,nombre,slug', 'pagos:id,cita_id,monto_centavos,moneda,estado'])
            ->orderByDesc('inicio_utc')
            ->limit(50)
            ->get();

        $resumenes = DB::table('resumenes')
            ->join('citas', 'citas.id', '=', 'resumenes.cita_id')
            ->where('citas.cliente_id', $cliente->id)
            ->orderByDesc('resumenes.created_at')
            ->limit(20)
            ->select(['resumenes.id', 'resumenes.cita_id', 'resumenes.contenido', 'resumenes.created_at', 'citas.uuid as cita_uuid', 'citas.inicio_utc', 'citas.tema_principal'])
            ->get();

        return response()->json([
            'data' => [
                'uuid' => $cliente->uuid,
                'name' => $cliente->name,
                'email' => $cliente->email,
                'email_verified_at' => $cliente->email_verified_at?->toIso8601String(),
                'created_at' => $cliente->created_at?->toIso8601String(),
                'last_login_at' => $cliente->last_login_at?->toIso8601String(),
                'profile' => $cliente->profile,
                'dato_natal' => $cliente->datoNatal,
                'preferencia_notificacion' => $cliente->preferenciaNotificacion,
                'consentimientos' => $cliente->consentimientos,
                'roles' => $cliente->roles->pluck('nombre'),
                'notas_admin' => $cliente->profile?->notas_admin,
                'notas_admin_actualizadas_en' => $cliente->profile?->notas_admin_actualizadas_en?->toIso8601String(),
                'stats' => $stats,
                'citas' => $citas->map(fn ($c) => [
                    'uuid' => $c->uuid,
                    'codigo_referencia' => $c->codigo_referencia,
                    'inicio_utc' => $c->inicio_utc?->toIso8601String(),
                    'duracion_minutos' => $c->duracion_minutos,
                    'estado' => $c->estado,
                    'tipo_consulta' => $c->tipoConsulta?->only(['nombre', 'slug']),
                    'precio_final_centavos' => $c->precio_final_centavos,
                    'moneda' => $c->moneda,
                    'tema_principal' => $c->tema_principal,
                ])->all(),
                'resumenes' => $resumenes,
            ],
        ]);
    }

    /**
     * GET /api/admin/clientes/{uuid}/estadisticas
     */
    public function estadisticas(string $uuid): JsonResponse
    {
        $cliente = User::query()->where('uuid', $uuid)->firstOrFail();
        return response()->json(['data' => $this->statsForCliente($cliente->id)]);
    }

    /**
     * PATCH /api/admin/clientes/{uuid}/notas
     */
    public function actualizarNotas(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'notas_admin' => ['nullable', 'string', 'max:20000'],
        ]);

        $cliente = User::query()->where('uuid', $uuid)->firstOrFail();
        $profile = $cliente->profile;
        if (! $profile) {
            return response()->json(['message' => 'El cliente no tiene perfil creado.'], 422);
        }

        $profile->notas_admin = $data['notas_admin'] ?? null;
        $profile->notas_admin_actualizadas_por = $request->user()->id;
        $profile->notas_admin_actualizadas_en = now();
        $profile->save();

        return response()->json([
            'data' => [
                'notas_admin' => $profile->notas_admin,
                'notas_admin_actualizadas_en' => $profile->notas_admin_actualizadas_en?->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /api/admin/clientes/{uuid}/briefing?cita_uuid=
     */
    public function briefing(Request $request, string $uuid, BriefingIAService $service): JsonResponse
    {
        $request->validate([
            'cita_uuid' => ['sometimes', 'string', 'uuid'],
        ]);

        $cliente = User::query()->where('uuid', $uuid)->firstOrFail();

        $cita = null;
        if ($citaUuid = $request->string('cita_uuid')->toString()) {
            $cita = Cita::query()->where('uuid', $citaUuid)->where('cliente_id', $cliente->id)->first();
            if (! $cita) {
                return response()->json(['message' => 'Cita no encontrada o no pertenece al cliente.'], 404);
            }
        }

        try {
            $resultado = $service->generar($cliente, $cita);
        } catch (RuntimeException $e) {
            return response()->json(['message' => 'No se pudo generar el briefing.', 'detalle' => $e->getMessage()], 502);
        }

        return response()->json(['data' => $resultado]);
    }

    private function statsForCliente(int $clienteId): array
    {
        return $this->statsBatch([$clienteId])[$clienteId] ?? $this->emptyStats();
    }

    /**
     * Calcula stats agregadas para un batch de clientes en pocas queries.
     *
     * @param array<int,int> $clienteIds
     * @return array<int, array<string,mixed>>
     */
    private function statsBatch(array $clienteIds): array
    {
        if (empty($clienteIds)) {
            return [];
        }

        $byEstado = Cita::query()
            ->whereIn('cliente_id', $clienteIds)
            ->select('cliente_id', 'estado', DB::raw('count(*) as total'))
            ->groupBy('cliente_id', 'estado')
            ->get();

        $ingresos = Pago::query()
            ->join('citas', 'citas.id', '=', 'pagos.cita_id')
            ->whereIn('citas.cliente_id', $clienteIds)
            ->where('pagos.estado', 'pagado')
            ->select('citas.cliente_id', 'pagos.moneda', DB::raw('coalesce(sum(pagos.monto_centavos),0) as total_centavos'))
            ->groupBy('citas.cliente_id', 'pagos.moneda')
            ->get();

        $primerasUltimas = Cita::query()
            ->whereIn('cliente_id', $clienteIds)
            ->where('estado', 'completada')
            ->select('cliente_id', DB::raw('min(inicio_utc) as primera'), DB::raw('max(inicio_utc) as ultima'))
            ->groupBy('cliente_id')
            ->get()->keyBy('cliente_id');

        $tipoTop = Cita::query()
            ->whereIn('cliente_id', $clienteIds)
            ->where('estado', 'completada')
            ->select('cliente_id', 'tipo_consulta_id', DB::raw('count(*) as total'))
            ->groupBy('cliente_id', 'tipo_consulta_id')
            ->orderByDesc('total')
            ->get()->groupBy('cliente_id');

        $tipoNombres = DB::table('tipos_consulta')->pluck('nombre', 'id');

        $out = [];
        foreach ($clienteIds as $cid) {
            $estados = $byEstado->where('cliente_id', $cid);
            $totalCompletadas = (int) $estados->where('estado', 'completada')->sum('total');
            $totalCanceladas = (int) $estados->whereIn('estado', ['cancelada_cliente', 'cancelada_chachita', 'cancelada_admin'])->sum('total');
            $totalNoShow = (int) $estados->where('estado', 'no_show')->sum('total');
            $totalConfirmadas = (int) $estados->where('estado', 'confirmada')->sum('total');

            $ingresosCliente = [];
            foreach ($ingresos->where('cliente_id', $cid) as $i) {
                $ingresosCliente[$i->moneda] = (int) $i->total_centavos;
            }

            $top = $tipoTop->get($cid);
            $tipoFav = null;
            if ($top && $top->isNotEmpty()) {
                $first = $top->first();
                $tipoFav = $tipoNombres[$first->tipo_consulta_id] ?? null;
            }

            $pu = $primerasUltimas->get($cid);

            $out[$cid] = [
                'total_completadas' => $totalCompletadas,
                'total_canceladas' => $totalCanceladas,
                'total_no_show' => $totalNoShow,
                'total_confirmadas' => $totalConfirmadas,
                'ingresos_centavos' => $ingresosCliente,
                'tipo_favorito' => $tipoFav,
                'primera_consulta' => $pu?->primera ? \Carbon\Carbon::parse($pu->primera)->toIso8601String() : null,
                'ultima_consulta' => $pu?->ultima ? \Carbon\Carbon::parse($pu->ultima)->toIso8601String() : null,
            ];
        }

        return $out;
    }

    private function emptyStats(): array
    {
        return [
            'total_completadas' => 0,
            'total_canceladas' => 0,
            'total_no_show' => 0,
            'total_confirmadas' => 0,
            'ingresos_centavos' => [],
            'tipo_favorito' => null,
            'primera_consulta' => null,
            'ultima_consulta' => null,
        ];
    }
}
