<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cupon extends Model
{
    use SoftDeletes;

    protected $table = 'cupones';

    protected $fillable = [
        'codigo', 'descripcion', 'tipo_descuento', 'valor_descuento',
        'moneda', 'uso_maximo_total', 'uso_maximo_por_cliente', 'usos_totales',
        'vigente_desde', 'vigente_hasta', 'monto_minimo_centavos',
        'solo_primera_consulta', 'activo',
    ];

    protected $casts = [
        'activo'                  => 'boolean',
        'solo_primera_consulta'   => 'boolean',
        'valor_descuento'         => 'float',
        'vigente_desde'           => 'datetime',
        'vigente_hasta'           => 'datetime',
        'monto_minimo_centavos'   => 'integer',
    ];

    public function estaVigente(): bool
    {
        if (! $this->activo) {
            return false;
        }
        $now = now();
        if ($now->lt($this->vigente_desde)) {
            return false;
        }
        if ($this->vigente_hasta && $now->gt($this->vigente_hasta)) {
            return false;
        }
        return true;
    }

    public function usosDisponibles(): bool
    {
        if ($this->uso_maximo_total === null) {
            return true;
        }
        return $this->usos_totales < $this->uso_maximo_total;
    }

    public function calcularDescuento(int $precioTotalCentavos): int
    {
        if ($this->tipo_descuento === 'porcentaje') {
            return (int) round($precioTotalCentavos * $this->valor_descuento / 100);
        }
        // monto_fijo: valor en unidad de moneda, convertir a centavos
        return min((int) round($this->valor_descuento * 100), $precioTotalCentavos);
    }
}
