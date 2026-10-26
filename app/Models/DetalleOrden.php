<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATaller;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleOrden extends Model
{
    use HasFactory, PerteneceATaller;

    protected $table = 'detalles_orden';

    protected $fillable = [
        'taller_id',
        'orden_servicio_id',
        'repuesto_id',
        'cantidad',
        'precio_unitario',
        'precio_unitario_original',
        'descuento_aplicado_pct',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'precio_unitario' => 'decimal:2',
            'precio_unitario_original' => 'decimal:2',
            'descuento_aplicado_pct' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function ordenServicio(): BelongsTo
    {
        return $this->belongsTo(OrdenServicio::class, 'orden_servicio_id');
    }

    public function repuesto(): BelongsTo
    {
        // withTrashed: una factura histórica debe seguir mostrando el
        // repuesto aunque luego se haya eliminado del inventario.
        return $this->belongsTo(Repuesto::class)->withTrashed();
    }
}
