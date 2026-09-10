<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TareaProgramada extends Model
{
    protected $table = 'tareas_programadas';

    const UPDATED_AT = null;

    protected $fillable = [
        'tipo',
        'orden_servicio_id',
        'fecha_envio',
        'respuesta_cliente',
    ];

    protected function casts(): array
    {
        return [
            'fecha_envio' => 'datetime',
        ];
    }

    public function ordenServicio(): BelongsTo
    {
        return $this->belongsTo(OrdenServicio::class, 'orden_servicio_id');
    }
}
