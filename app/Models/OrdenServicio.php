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
        'numero_factura',
        'mano_obra',
        'observaciones_tecnicas',
        'liquidada_at',
        'subtotal_repuestos',
        'descuento_total',
        'base_gravable',
        'iva',
        'tasa_iva_aplicada',
        'precios_incluian_iva',
        'metodo_pago',
        'pagada_at',
    ];

    /**
     * Regla 3: el método de pago es interno. Oculto en toArray()/toJSON()
     * para que nunca se filtre a la factura JSON ni a respuestas al cliente.
     */
    protected $hidden = [
        'metodo_pago',
    ];

    public const ESTADOS = [
        'en_espera_revision',
        'en_revision',
        'reparacion',
        'espera_repuesto',
        'terminada',
        'listo_para_reclamar',
        'pagada',
    ];

    public const METODOS_PAGO = [
        'efectivo' => 'Efectivo',
        'transferencia' => 'Transferencia',
        'nequi' => 'Nequi',
        'daviplata' => 'Daviplata',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora_ingreso' => 'datetime',
            'ultima_interaccion_cliente' => 'datetime',
            'total' => 'decimal:2',
            'numero_factura' => 'integer',
            'mano_obra' => 'decimal:2',
            'liquidada_at' => 'datetime',
            'subtotal_repuestos' => 'decimal:2',
            'descuento_total' => 'decimal:2',
            'base_gravable' => 'decimal:2',
            'iva' => 'decimal:2',
            'tasa_iva_aplicada' => 'decimal:2',
            'precios_incluian_iva' => 'boolean',
            'pagada_at' => 'datetime',
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

    /** Bitácora de auditoría, de la más antigua a la más reciente. */
    public function historialEstados(): HasMany
    {
        return $this->hasMany(OrdenEstadoHistorial::class, 'orden_servicio_id')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * Agrega una fila a la bitácora. El taller_id sale de la orden y no del
     * TenantManager: así también funciona para el SuperAdmin (taller null).
     */
    public function registrarHistorial(
        string $evento,
        ?string $estadoAnterior,
        ?User $user,
        ?string $observaciones = null,
        ?int $mecanicoId = null,
    ): OrdenEstadoHistorial {
        return OrdenEstadoHistorial::create([
            'taller_id' => $this->taller_id,
            'orden_servicio_id' => $this->id,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $this->estado,
            'evento' => $evento,
            'user_id' => $user?->id,
            'mecanico_id' => $mecanicoId,
            'observaciones' => $observaciones,
        ]);
    }

    /** Para el badge "Corregida N veces": withCount('correcciones'). */
    public function correcciones(): HasMany
    {
        return $this->hasMany(OrdenEstadoHistorial::class, 'orden_servicio_id')
            ->where('evento', 'correccion');
    }

    // --- Factura ---
    // La factura no es una entidad aparte: es la propia orden liquidada
    // (numero_factura + totales congelados + detalles).

    public function estaFacturada(): bool
    {
        return $this->numero_factura !== null
            && in_array($this->estado, ['listo_para_reclamar', 'pagada'], true);
    }

    /** Solo para vistas internas; vacío en órdenes pagadas antes del Bloque 3. */
    public function metodoPagoEtiqueta(): string
    {
        return self::METODOS_PAGO[$this->metodo_pago] ?? 'Sin registrar';
    }

    // --- Semáforo por antigüedad (desde fecha_hora_ingreso) ---

    public function diasEnTaller(): int
    {
        return (int) floor($this->fecha_hora_ingreso->diffInDays(now()));
    }

    /** @return 'verde'|'amarillo'|'rojo' */
    public function semaforo(ConfiguracionTaller $config): string
    {
        $dias = $this->diasEnTaller();

        return match (true) {
            $dias > $config->dias_alerta_roja => 'rojo',
            $dias >= $config->dias_alerta_amarilla => 'amarillo',
            default => 'verde',
        };
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
     * Filtro explícito por taller. El TenantScope ya filtra para usuarios de
     * un taller; esto sirve al SuperAdmin (taller_id null, ve todo) y a jobs.
     */
    public function scopeDelTaller($query, int $tallerId)
    {
        return $query->where($this->qualifyColumn('taller_id'), $tallerId);
    }

    /** Cuentas por cobrar: liquidadas, aún no pagadas. */
    public function scopePorPagar($query)
    {
        return $query->where('estado', 'listo_para_reclamar');
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
