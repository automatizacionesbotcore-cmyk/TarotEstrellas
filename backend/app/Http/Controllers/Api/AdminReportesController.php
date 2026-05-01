<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Pago;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminReportesController extends Controller
{
    public function ingresos(Request $request): JsonResponse
    {
        $request->validate([
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date',
            'moneda' => 'nullable|string|size:3',
        ]);

        $desde = $request->input('desde') ? now()->parse($request->input('desde'))->startOfDay() : now()->startOfMonth();
        $hasta = $request->input('hasta') ? now()->parse($request->input('hasta'))->endOfDay() : now()->endOfDay();
        $moneda = strtoupper((string) $request->input('moneda', ''));

        $query = Pago::query()
            ->where('estado', 'completado')
            ->whereBetween('pagado_en', [$desde, $hasta]);

        if ($moneda !== '') {
            $query->where('moneda', $moneda);
        }

        $pagos = $query
            ->with(['cita:id,uuid,especialista_id', 'cita.tipoConsulta:id,nombre'])
            ->orderBy('pagado_en', 'desc')
            ->get();

        $resumenPorMoneda = $pagos->groupBy('moneda')->map(fn ($group, $mon) => [
            'moneda' => $mon,
            'total_centavos' => $group->sum('monto_centavos'),
            'cantidad' => $group->count(),
        ])->values();

        $pagosFormateados = $pagos->map(fn (Pago $p) => [
            'uuid' => $p->uuid,
            'tipo' => $p->tipo,
            'canal' => $p->canal,
            'monto_centavos' => $p->monto_centavos,
            'moneda' => $p->moneda,
            'pagado_en' => $p->pagado_en,
            'cita_uuid' => $p->cita?->uuid,
            'servicio' => $p->cita?->tipoConsulta?->nombre,
        ]);

        return response()->json([
            'data' => [
                'desde' => $desde->toDateString(),
                'hasta' => $hasta->toDateString(),
                'resumen' => $resumenPorMoneda,
                'pagos' => $pagosFormateados,
            ],
        ]);
    }

    public function consultas(Request $request): JsonResponse
    {
        $request->validate([
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date',
        ]);

        $desde = $request->input('desde') ? now()->parse($request->input('desde'))->startOfDay() : now()->startOfMonth();
        $hasta = $request->input('hasta') ? now()->parse($request->input('hasta'))->endOfDay() : now()->endOfDay();

        $citas = Cita::whereBetween('inicio_utc', [$desde, $hasta])
            ->with('tipoConsulta:id,nombre')
            ->get();

        $total        = $citas->count();
        $porEstado    = $citas->groupBy('estado')->map->count()->sortDesc();
        $porTipo      = $citas->groupBy(fn ($c) => $c->tipoConsulta?->nombre ?? 'Sin tipo')->map->count()->sortDesc();
        $canceladas   = $citas->filter(fn ($c) => in_array($c->estado, ['cancelada_cliente', 'cancelada_especialista', 'cancelada']))->count();
        $noShow       = $citas->where('estado', 'no_show')->count();
        $finalizadas  = $citas->whereIn('estado', ['finalizada', 'completada'])->count();

        $duracionPromedio = $citas->whereIn('estado', ['finalizada', 'completada'])->avg('duracion_minutos') ?? 0;

        return response()->json([
            'data' => [
                'desde'             => $desde->toDateString(),
                'hasta'             => $hasta->toDateString(),
                'total'             => $total,
                'finalizadas'       => $finalizadas,
                'canceladas'        => $canceladas,
                'no_show'           => $noShow,
                'tasa_cancelacion'  => $total > 0 ? round($canceladas / $total * 100, 1) : 0,
                'tasa_no_show'      => $total > 0 ? round($noShow / $total * 100, 1) : 0,
                'duracion_promedio' => round($duracionPromedio),
                'por_estado'        => $porEstado,
                'por_tipo'          => $porTipo,
            ],
        ]);
    }

    public function clientes(Request $request): JsonResponse
    {
        $request->validate([
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date',
        ]);

        $desde = $request->input('desde') ? now()->parse($request->input('desde'))->startOfDay() : now()->startOfMonth();
        $hasta = $request->input('hasta') ? now()->parse($request->input('hasta'))->endOfDay() : now()->endOfDay();

        $nuevosClientes = User::whereBetween('created_at', [$desde, $hasta])
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('slug', ['admin', 'super_admin', 'especialista']))
            ->count();

        $topPorIngresos = DB::table('pagos')
            ->join('citas', 'pagos.cita_id', '=', 'citas.id')
            ->join('users', 'citas.cliente_id', '=', 'users.id')
            ->where('pagos.estado', 'completado')
            ->whereBetween('pagos.pagado_en', [$desde, $hasta])
            ->select('users.id', 'users.name', 'users.email',
                DB::raw('SUM(pagos.monto_centavos) as total_centavos'),
                DB::raw('MAX(pagos.moneda) as moneda'))
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('total_centavos')
            ->limit(10)
            ->get();

        $topPorConsultas = DB::table('citas')
            ->join('users', 'citas.cliente_id', '=', 'users.id')
            ->whereIn('citas.estado', ['finalizada', 'completada'])
            ->whereBetween('citas.inicio_utc', [$desde, $hasta])
            ->select('users.id', 'users.name', 'users.email',
                DB::raw('COUNT(citas.id) as total_consultas'))
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('total_consultas')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => [
                'desde'             => $desde->toDateString(),
                'hasta'             => $hasta->toDateString(),
                'nuevos_clientes'   => $nuevosClientes,
                'top_ingresos'      => $topPorIngresos,
                'top_consultas'     => $topPorConsultas,
            ],
        ]);
    }

    public function fiscal(Request $request): JsonResponse
    {
        $request->validate([
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date',
        ]);

        $desde = $request->input('desde') ? now()->parse($request->input('desde'))->startOfDay() : now()->startOfMonth();
        $hasta = $request->input('hasta') ? now()->parse($request->input('hasta'))->endOfDay() : now()->endOfDay();

        $pagos = Pago::query()
            ->where('estado', 'completado')
            ->whereBetween('pagado_en', [$desde, $hasta])
            ->with('cita.tipoConsulta:id,nombre')
            ->get();

        $totalBrutoCentavos = $pagos->sum('monto_centavos');
        $comisionesEstimadas = (int) round($totalBrutoCentavos * 0.029 + count($pagos) * 30);

        $desglosePorTipo = $pagos->groupBy(fn ($p) => $p->cita?->tipoConsulta?->nombre ?? 'Sin tipo')
            ->map(fn ($grupo, $tipo) => [
                'tipo'            => $tipo,
                'total_centavos'  => $grupo->sum('monto_centavos'),
                'cantidad_pagos'  => $grupo->count(),
                'moneda'          => $grupo->first()->moneda ?? 'CLP',
            ])->values();

        $desglosePorMes = $pagos->groupBy(fn ($p) => substr((string) $p->pagado_en, 0, 7))
            ->map(fn ($grupo, $mes) => [
                'mes'            => $mes,
                'total_centavos' => $grupo->sum('monto_centavos'),
                'cantidad_pagos' => $grupo->count(),
            ])->sortKeys()->values();

        return response()->json([
            'data' => [
                'desde'                      => $desde->toDateString(),
                'hasta'                      => $hasta->toDateString(),
                'ingresos_brutos_centavos'   => $totalBrutoCentavos,
                'comisiones_stripe_centavos' => $comisionesEstimadas,
                'ingresos_netos_centavos'    => $totalBrutoCentavos - $comisionesEstimadas,
                'desglose_por_tipo'          => $desglosePorTipo,
                'desglose_por_mes'           => $desglosePorMes,
            ],
        ]);
        }

    public function exportCsv(Request $request)
    {
        $tipo = $request->string('tipo')->toString() ?: 'ingresos';
        $desde = $request->input('desde') ? now()->parse($request->input('desde'))->startOfDay() : now()->startOfMonth();
        $hasta = $request->input('hasta') ? now()->parse($request->input('hasta'))->endOfDay() : now()->endOfDay();

        $callback = function () use ($tipo, $desde, $hasta) {
            $out = fopen('php://output', 'w');
            if ($tipo === 'ingresos') {
                fputcsv($out, ['fecha', 'tipo', 'canal', 'monto_centavos', 'moneda']);
                Pago::query()->where('estado', 'completado')
                    ->whereBetween('pagado_en', [$desde, $hasta])
                    ->orderBy('pagado_en')
                    ->chunk(500, function ($rows) use ($out) {
                        foreach ($rows as $p) {
                            fputcsv($out, [optional($p->pagado_en)->toDateTimeString(), $p->tipo, $p->canal, $p->monto_centavos, $p->moneda]);
                        }
                    });
            } elseif ($tipo === 'consultas') {
                fputcsv($out, ['inicio_utc', 'estado', 'cliente_id', 'tipo_id', 'precio_centavos']);
                Cita::query()->whereBetween('inicio_utc', [$desde, $hasta])
                    ->orderBy('inicio_utc')
                    ->chunk(500, function ($rows) use ($out) {
                        foreach ($rows as $c) {
                            fputcsv($out, [optional($c->inicio_utc)->toDateTimeString(), $c->estado, $c->cliente_id, $c->tipo_consulta_id, $c->precio_final_centavos]);
                        }
                    });
            } elseif ($tipo === 'clientes') {
                fputcsv($out, ['id', 'nombre', 'email', 'creado_en']);
                User::query()->whereBetween('created_at', [$desde, $hasta])
                    ->orderBy('id')
                    ->chunk(500, function ($rows) use ($out) {
                        foreach ($rows as $u) {
                            fputcsv($out, [$u->id, $u->name, $u->email, optional($u->created_at)->toDateTimeString()]);
                        }
                    });
            }
            fclose($out);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="reporte_'.$tipo.'_'.now()->format('Ymd_His').'.csv"',
        ]);
    }
}