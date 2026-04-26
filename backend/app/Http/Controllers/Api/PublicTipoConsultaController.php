<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TipoConsulta;
use App\Models\TipoConsultaPrecio;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicTipoConsultaController extends Controller
{
    // Categorías definidas por slugs (la tabla no tiene campo categoría, se deriva del slug)
    private const CATEGORIAS = [
        'tarot' => ['tarot'],
        'astrologia' => ['carta-astral', 'astrologia-revolucion-solar', 'sinastria'],
        'otros' => ['cartas-espanolas', 'consulta-rapida', 'limpieza-energetica', 'numerologia', 'runas', 'lectura-cafe', 'quiromancia', 'pendulo'],
    ];

    public function index(Request $request): JsonResponse
    {
        $moneda = $request->query('moneda', 'CLP');
        $query = TipoConsulta::query()->where('activo', true);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('descripcion', 'like', "%{$search}%");
            });
        }

        if ($duracion = $request->query('duracion')) {
            $query->where('duracion_minutos', '<=', (int) $duracion);
        }

        if ($request->query('requiere_datos_natales') !== null) {
            $val = filter_var($request->query('requiere_datos_natales'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($val !== null) {
                $query->where('requiere_datos_natales', $val);
            }
        }

        if ($categoria = $request->query('categoria')) {
            $slugs = self::CATEGORIAS[$categoria] ?? null;
            if ($slugs) {
                $query->whereIn('slug', $slugs);
            }
        }

        $tipos = $query
            ->orderBy('orden_visualizacion')
            ->orderBy('nombre')
            ->get();

        $hoy = Carbon::today()->format('Y-m-d');
        $tipos->each(function ($tipo) use ($moneda, $hoy) {
            $tipo->precio_moneda = $this->getPrecio($tipo->id, $moneda, $hoy);
            $tipo->moneda_solicitada = $moneda;
            $tipo->precios = $this->getPreciosTodos($tipo->id, $hoy);
        });

        return response()->json(['data' => $tipos]);
    }

    public function show(string $slug): JsonResponse
    {
        $tipo = TipoConsulta::query()
            ->where('slug', $slug)
            ->where('activo', true)
            ->firstOrFail();

        $hoy = Carbon::today()->format('Y-m-d');
        $tipo->precios = $this->getPreciosTodos($tipo->id, $hoy);

        return response()->json(['data' => $tipo]);
    }

    private function getPrecio(int $tipoId, string $moneda, string $hoy): ?array
    {
        $precio = TipoConsultaPrecio::query()
            ->where('tipo_consulta_id', $tipoId)
            ->where('moneda', $moneda)
            ->where('vigente_desde', '<=', $hoy)
            ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $hoy))
            ->orderByDesc('vigente_desde')
            ->first();

        if (! $precio) {
            return null;
        }

        return ['moneda' => $precio->moneda, 'precio_centavos' => $precio->precio_centavos];
    }

    private function getPreciosTodos(int $tipoId, string $hoy): array
    {
        return TipoConsultaPrecio::query()
            ->where('tipo_consulta_id', $tipoId)
            ->where('vigente_desde', '<=', $hoy)
            ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $hoy))
            ->orderBy('moneda')
            ->get(['moneda', 'precio_centavos'])
            ->toArray();
    }
}
