<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgenteConversacion;
use App\Support\AgenteCostos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAgenteMetricsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'desde' => ['sometimes', 'date'],
            'hasta' => ['sometimes', 'date'],
        ]);

        $desde = $request->date('desde') ?: now()->subDays(30)->startOfDay();
        $hasta = $request->date('hasta') ?: now()->endOfDay();

        $base = AgenteConversacion::query()
            ->whereBetween('created_at', [$desde, $hasta]);

        // Totales (uno por modelo para calcular costo por modelo y luego sumar)
        $porModelo = (clone $base)
            ->select('modelo',
                DB::raw('COUNT(*) as conv'),
                DB::raw('COALESCE(SUM(tokens_in),0)  as tokens_in'),
                DB::raw('COALESCE(SUM(tokens_out),0) as tokens_out'),
                DB::raw('COALESCE(AVG(latencia_ms),0) as latencia_avg'),
                DB::raw('SUM(CASE WHEN error IS NOT NULL THEN 1 ELSE 0 END) as errores')
            )
            ->groupBy('modelo')
            ->get()
            ->map(function ($r) {
                $r->costo_usd = AgenteCostos::costo($r->modelo, (int) $r->tokens_in, (int) $r->tokens_out);
                $r->precio    = AgenteCostos::precio($r->modelo);
                return $r;
            });

        $totales = [
            'conversaciones' => (int) $porModelo->sum('conv'),
            'tokens_in'      => (int) $porModelo->sum('tokens_in'),
            'tokens_out'     => (int) $porModelo->sum('tokens_out'),
            'errores'        => (int) $porModelo->sum('errores'),
            'costo_usd'      => round((float) $porModelo->sum('costo_usd'), 6),
            'latencia_avg_ms'=> (int) round((float) ($porModelo->avg('latencia_avg') ?? 0)),
        ];

        // Top usuarios por costo
        $porUsuario = (clone $base)
            ->select('cliente_id',
                DB::raw('COUNT(*) as conv'),
                DB::raw('COALESCE(SUM(tokens_in),0)  as tokens_in'),
                DB::raw('COALESCE(SUM(tokens_out),0) as tokens_out'),
                'modelo'
            )
            ->groupBy('cliente_id', 'modelo')
            ->get()
            ->groupBy('cliente_id')
            ->map(function ($rows, $cid) {
                $costo = 0;
                $in = 0; $out = 0; $conv = 0;
                foreach ($rows as $r) {
                    $costo += AgenteCostos::costo($r->modelo, (int) $r->tokens_in, (int) $r->tokens_out);
                    $in += (int) $r->tokens_in;
                    $out += (int) $r->tokens_out;
                    $conv += (int) $r->conv;
                }
                return [
                    'cliente_id' => (int) $cid,
                    'conversaciones' => $conv,
                    'tokens_in'  => $in,
                    'tokens_out' => $out,
                    'costo_usd'  => round($costo, 6),
                ];
            })
            ->sortByDesc('costo_usd')
            ->values()
            ->take(10);

        // Serie diaria
        $serie = (clone $base)
            ->select(DB::raw("DATE(created_at) as dia"),
                DB::raw('COUNT(*) as conv'),
                DB::raw('COALESCE(SUM(tokens_in),0)  as tokens_in'),
                DB::raw('COALESCE(SUM(tokens_out),0) as tokens_out'),
                'modelo'
            )
            ->groupBy('dia', 'modelo')
            ->orderBy('dia')
            ->get()
            ->groupBy('dia')
            ->map(function ($rows, $dia) {
                $costo = 0; $conv = 0;
                foreach ($rows as $r) {
                    $costo += AgenteCostos::costo($r->modelo, (int) $r->tokens_in, (int) $r->tokens_out);
                    $conv += (int) $r->conv;
                }
                return ['dia' => $dia, 'conversaciones' => $conv, 'costo_usd' => round($costo, 6)];
            })
            ->values();

        return response()->json([
            'desde'        => $desde,
            'hasta'        => $hasta,
            'totales'      => $totales,
            'por_modelo'   => $porModelo,
            'top_usuarios' => $porUsuario,
            'serie_diaria' => $serie,
            'precios'      => AgenteCostos::PRECIOS,
        ]);
    }
}
