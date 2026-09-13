<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\PerteneceATaller;


class OrdenServicio extends Model
{
    use HasFactory, PerteneceATaller;

    protected $table = 'ordenes_servicio';

    protected $fillable = [
        'fecha_hora_ingreso',
        'ultima_interaccion_cliente',
        'estado',
        'descripcion_falla',
        'mecanico_id',
        'cliente_id',
        'moto_id',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora_ingreso' => 'datetime',
            'ultima_interaccion_cliente' => 'datetime',
            'total' => 'decimal:2',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function moto(): BelongsTo
    {
        return $this->belongsTo(Moto::class);
    }

    public function mecanico(): BelongsTo
    {
        return $this->belongsTo(Mecanico::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleOrden::class, 'orden_servicio_id');
    }

    public function tareasProgramadas(): HasMany
    {
        return $this->hasMany(TareaProgramada::class, 'orden_servicio_id');
    }

    public function historialMantenimiento(): HasMany
    {
        return $this->hasMany(HistorialMantenimiento::class, 'orden_servicio_id');
    }

    // --- Scopes por estado (usados en el Kanban de Fase 3) ---

    public function scopeEnEsperaRevision($query)
    {
        return $query->where('estado', 'en_espera_revision');
    }

    public function scopeEnRevision($query)
    {
        return $query->where('estado', 'en_revision');
    }

    public function scopeEnReparacion($query)
    {
        return $query->where('estado', 'reparacion');
    }

    public function scopeEsperaRepuesto($query)
    {
        return $query->where('estado', 'espera_repuesto');
    }

    public function scopeTerminada($query)
    {
        return $query->where('estado', 'terminada');
    }

    public function scopeListoParaReclamar($query)
    {
        return $query->where('estado', 'listo_para_reclamar');
    }

    public function scopePagada($query)
    {
        return $query->where('estado', 'pagada');
    }

    /**
     * Órdenes que ocupan capacidad activa del taller (para validar el
     * límite de capacidad_maxima_simultanea antes de mover a en_revision/reparacion).
     */
    public function scopeActivasEnTaller($query)
    {
        return $query->whereIn('estado', ['en_revision', 'reparacion', 'espera_repuesto']);
    }
}
