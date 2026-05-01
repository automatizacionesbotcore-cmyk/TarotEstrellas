<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MonedaConverterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonedaController extends Controller
{
    public function __construct(private MonedaConverterService $svc)
    {
    }

    public function detectar(Request $request): JsonResponse
    {
        $cf = $request->header('CF-IPCountry');
        $iso = is_string($cf) ? strtoupper($cf) : null;
        $moneda = $this->svc->monedaParaPais($iso);
        return response()->json(['data' => [
            'pais' => $iso,
            'moneda' => $moneda,
            'tasas' => $this->svc->tasas(),
        ]]);
    }

    public function convertir(Request $request): JsonResponse
    {
        $request->validate([
            'centavos' => ['required', 'integer', 'min:0'],
            'origen' => ['required', 'string', 'size:3'],
            'destino' => ['required', 'string', 'size:3'],
        ]);
        $r = $this->svc->convertir((int) $request->integer('centavos'), $request->string('origen'), $request->string('destino'));
        return response()->json(['data' => ['centavos' => $r]]);
    }
}
