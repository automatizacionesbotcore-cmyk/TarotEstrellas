<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificacionEnviada;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificacionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'canal' => ['sometimes', 'in:email,whatsapp,sms,push'],
            'estado' => ['sometimes', 'in:enviado,error,rebotado,leido'],
            'tipo' => ['sometimes', 'string', 'max:80'],
            'destinatario' => ['sometimes', 'string', 'max:191'],
            'desde' => ['sometimes', 'date'],
            'hasta' => ['sometimes', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $q = NotificacionEnviada::query()->with('user:id,uuid,name,email');
        foreach (['canal', 'estado', 'tipo'] as $f) {
            if ($v = $request->string($f)->toString()) $q->where($f, $v);
        }
        if ($d = $request->string('destinatario')->toString()) {
            $q->where('destinatario', 'like', "%$d%");
        }
        if ($desde = $request->date('desde')) $q->where('enviado_en', '>=', $desde);
        if ($hasta = $request->date('hasta')) $q->where('enviado_en', '<=', $hasta->endOfDay());

        return response()->json($q->orderByDesc('enviado_en')
            ->paginate((int) $request->integer('per_page', 25)));
    }

    public function metrics(): JsonResponse
    {
        $base = NotificacionEnviada::query();
        $hoy = (clone $base)->whereDate('enviado_en', today())->count();
        $errores = (clone $base)->where('estado', 'error')->whereDate('enviado_en', today())->count();
        $porCanal = (clone $base)->whereDate('enviado_en', today())
            ->selectRaw('canal, count(*) as total')
            ->groupBy('canal')->pluck('total', 'canal');
        return response()->json(['data' => compact('hoy', 'errores', 'porCanal')]);
    }
}
