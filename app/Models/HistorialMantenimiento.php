<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATaller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialMantenimiento extends Model
{
    use PerteneceATaller;

    protected $table = 'historial_mantenimiento';

    const UPDATED_AT = null;

    protected $fillable = [
        'taller_id',
        'orden_servicio_id',
        'repuesto_id',
        'fecha_cambio_repuesto',
        'vida_util_km',
        'vida_util_meses',
        'plantilla_whatsapp_enviada',
    ];

    protected function casts(): array
    {
        return [
            'fecha_cambio_repuesto' => 'datetime',
            'plantilla_whatsapp_enviada' => 'boolean',
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

    public function scopePendientesDeRecordatorio($query)
    {
        return $query->where('plantilla_whatsapp_enviada', false)
            ->where('fecha_cambio_repuesto', '<=', now()->subMonths(3));
    }
}
