<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consentimiento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConsentimientoController extends Controller
{
    private const TIPOS_VALIDOS = [
        'terminos_uso',
        'politica_privacidad',
        'marketing_email',
        'marketing_whatsapp',
        'cookies_analitica',
        'almacenamiento_grabacion',
    ];

    public function index(Request $request): JsonResponse
    {
        $consentimientos = Consentimiento::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('otorgado_en')
            ->get();

        $vigentes = $consentimientos
            ->groupBy('tipo')
            ->map(fn ($items) => $items->first())
            ->values();

        return response()->json([
            'data'     => $consentimientos,
            'vigentes' => $vigentes,
            'tipos'    => self::TIPOS_VALIDOS,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tipo'              => ['required', Rule::in(self::TIPOS_VALIDOS)],
            'otorgado'          => ['required', 'boolean'],
            'version_documento' => ['nullable', 'string', 'max:30'],
        ]);

        $registro = Consentimiento::create([
            'user_id'           => $request->user()->id,
            'tipo'              => $data['tipo'],
            'version_documento' => $data['version_documento'] ?? '1.0',
            'otorgado'          => $data['otorgado'],
            'otorgado_en'       => now(),
            'ip_otorgamiento'   => $request->ip(),
            'user_agent'        => substr((string) $request->userAgent(), 0, 255),
        ]);

        return response()->json(['data' => $registro], 201);
    }
}
