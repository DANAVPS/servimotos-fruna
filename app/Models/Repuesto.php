<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Repuesto extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'marca',
        'modelo_compatible',
        'precio_costo',
        'precio_venta',
        'stock_actual',
        'stock_minimo',
    ];

    protected function casts(): array
    {
        return [
            'precio_costo' => 'decimal:2',
            'precio_venta' => 'decimal:2',
        ];
    }

    public function detallesOrden(): HasMany
    {
        return $this->hasMany(DetalleOrden::class);
    }

    public function historialMantenimiento(): HasMany
    {
        return $this->hasMany(HistorialMantenimiento::class);
    }

    /**
     * Repuestos cuyo stock está por debajo del mínimo configurado.
     * La lógica predictiva de "5 días hábiles" vive en InventarioService (Fase 2),
     * este scope cubre el caso simple de stock bajo el umbral fijo.
     */
    public function scopeStockBajo($query)
    {
        return $query->whereColumn('stock_actual', '<=', 'stock_minimo');
    }
}
