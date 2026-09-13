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
        'subtotal',
        'mano_obra',
    ];

    protected function casts(): array
    {
        return [
            'precio_unitario' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'mano_obra' => 'decimal:2',
        ];
    }

    public function ordenServicio(): BelongsTo
    {
        return $this->belongsTo(OrdenServicio::class, 'orden_servicio_id');
    }

    public function repuesto(): BelongsTo
    {
        return $this->belongsTo(Repuesto::class);
    }
}
