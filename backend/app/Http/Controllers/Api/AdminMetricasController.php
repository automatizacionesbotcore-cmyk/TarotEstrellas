<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Pago;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminMetricasController extends Controller
{
    public function index(): JsonResponse
    {
        $citas = $this->citasMetricas();
        $pagos = $this->pagosMetricas();
        $servicios = $this->serviciosMetricas();
        $clientes = $this->clientesMetricas();

        return response()->json([
            'data' => compact('citas', 'pagos', 'servicios', 'clientes'),
        ]);
    }

    private function citasMetricas(): array
    {
        $byEstado = Cita::query()
            ->select('estado', DB::raw('count(*) as total'), DB::raw('coalesce(sum(precio_final_centavos), 0) as monto_centavos'))
            ->groupBy('estado')
            ->get()
            ->keyBy('estado')
            ->map(fn ($r) => ['total' => $r->total, 'monto_centavos' => (int) $r->monto_centavos])
            ->toArray();

        $total = Cita::count();
        $completadas = ($byEstado['finalizada']['total'] ?? 0) + ($byEstado['completada']['total'] ?? 0);
        $canceladas = ($byEstado['cancelada_cliente']['total'] ?? 0) + ($byEstado['cancelada_especialista']['total'] ?? 0) + ($byEstado['cancelada']['total'] ?? 0);
        $tasaCancelacion = $total > 0 ? round(($canceladas / $total) * 100, 1) : 0;

        $ingresoTotal = Pago::where('estado', 'completado')->sum('monto_centavos');

        return [
            'total' => $total,
            'completadas' => $completadas,
            'canceladas' => $canceladas,
            'tasa_cancelacion' => $tasaCancelacion,
            'ingreso_total_centavos' => (int) $ingresoTotal,
            'by_estado' => $byEstado,
        ];
    }

    private function pagosMetricas(): array
    {
        $byCanal = Pago::query()
            ->where('estado', 'completado')
            ->select('canal', DB::raw('count(*) as total'), DB::raw('coalesce(sum(monto_centavos), 0) as monto_centavos'))
            ->groupBy('canal')
            ->get()
            ->keyBy('canal')
            ->map(fn ($r) => ['total' => $r->total, 'monto_centavos' => (int) $r->monto_centavos])
            ->toArray();

        $byTipo = Pago::query()
            ->where('estado', 'completado')
            ->select('tipo', DB::raw('count(*) as total'), DB::raw('coalesce(sum(monto_centavos), 0) as monto_centavos'))
            ->groupBy('tipo')
            ->get()
            ->keyBy('tipo')
            ->map(fn ($r) => ['total' => $r->total, 'monto_centavos' => (int) $r->monto_centavos])
            ->toArray();

        $totalPagos = Pago::where('estado', 'completado')->count();
        $montoTotal = Pago::where('estado', 'completado')->sum('monto_centavos');
        $pendientes = Pago::where('estado', 'pendiente')->count();

        return [
            'total' => $totalPagos,
            'monto_total_centavos' => (int) $montoTotal,
            'pendientes' => $pendientes,
            'by_canal' => $byCanal,
            'by_tipo' => $byTipo,
        ];
    }

    private function serviciosMetricas(): array
    {
        return Cita::query()
            ->join('tipos_consulta', 'citas.tipo_consulta_id', '=', 'tipos_consulta.id')
            ->select(
                'tipos_consulta.nombre',
                DB::raw('count(*) as total_citas'),
                DB::raw('sum(case when citas.estado in ("finalizada","completada") then 1 else 0 end) as completadas'),
                DB::raw('coalesce(sum(citas.precio_final_centavos), 0) as monto_centavos'),
            )
            ->groupBy('tipos_consulta.id', 'tipos_consulta.nombre')
            ->orderByDesc('total_citas')
            ->get()
            ->map(fn ($r) => [
                'nombre' => $r->nombre,
                'total_citas' => $r->total_citas,
                'completadas' => (int) $r->completadas,
                'monto_centavos' => (int) $r->monto_centavos,
            ])
            ->toArray();
    }

    private function clientesMetricas(): array
    {
        $totalClientes = Cita::distinct('cliente_id')->count('cliente_id');

        $topClientes = Cita::query()
            ->join('users', 'citas.cliente_id', '=', 'users.id')
            ->leftJoin('user_profiles', 'users.id', '=', 'user_profiles.user_id')
            ->select(
                'user_profiles.nombre',
                'users.email',
                DB::raw('count(*) as total_citas'),
                DB::raw('coalesce(sum(citas.precio_final_centavos), 0) as monto_centavos'),
            )
            ->groupBy('users.id', 'user_profiles.nombre', 'users.email')
            ->orderByDesc('total_citas')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'nombre' => $r->nombre ?? '',
                'email' => $r->email,
                'total_citas' => $r->total_citas,
                'monto_centavos' => (int) $r->monto_centavos,
            ])
            ->toArray();

        return [
            'total_clientes' => $totalClientes,
            'top_clientes' => $topClientes,
        ];
    }
}
