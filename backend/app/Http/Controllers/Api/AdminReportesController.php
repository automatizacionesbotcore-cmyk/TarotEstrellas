<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pago;
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
}
