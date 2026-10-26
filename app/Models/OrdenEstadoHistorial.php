<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATaller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Bitácora de auditoría de una orden: cada cambio de estado, liquidación,
 * corrección (con su motivo) y pago. Es inmutable: solo se insertan filas.
 */
class OrdenEstadoHistorial extends Model
{
    use PerteneceATaller;

    protected $table = 'orden_estado_historial';

    public const UPDATED_AT = null;

    public const EVENTOS = ['creacion', 'cambio_estado', 'liquidacion', 'correccion', 'pago'];

    protected $fillable = [
        'taller_id',
        'orden_servicio_id',
        'estado_anterior',
        'estado_nuevo',
        'evento',
        'user_id',
        'mecanico_id',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('El historial de estados de una orden no se modifica.');
        });

        static::deleting(function () {
            throw new LogicException('El historial de estados de una orden no se borra.');
        });
    }

    public function ordenServicio(): BelongsTo
    {
        return $this->belongsTo(OrdenServicio::class, 'orden_servicio_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function mecanico(): BelongsTo
    {
        return $this->belongsTo(Mecanico::class);
    }
}
