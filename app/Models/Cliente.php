<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATaller;
use App\Scopes\ClienteActivoScope;
use App\Support\Identificadores;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use HasFactory, PerteneceATaller;

    public const ESTADOS = ['activo', 'hibernando', 'archivado'];

    protected $fillable = [
        'tipo_cliente_id',
        'nombre',
        'cedula_nit',
        'telefono',
        'correo',
        'fecha_ultima_visita',
        'estado_cliente',
    ];

    protected function casts(): array
    {
        return [
            'fecha_ultima_visita' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new ClienteActivoScope());

        // Todo cliente nace con tipo: si no se eligió, Regular del taller.
        static::creating(function (Cliente $cliente) {
            if (empty($cliente->tipo_cliente_id) && $cliente->taller_id) {
                $cliente->tipo_cliente_id = TipoCliente::regularDelTaller($cliente->taller_id)->id;
            }
        });
    }

    /**
     * Las rutas /clientes/{cliente} también deben encontrar archivados (para
     * verlos, cambiarles el estado o reinscribirlos). El TenantScope sigue aplicando.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->withoutGlobalScope(ClienteActivoScope::class)
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }

    protected function cedulaNit(): Attribute
    {
        return Attribute::make(set: fn (?string $valor) => Identificadores::cedulaNit($valor));
    }

    protected function telefono(): Attribute
    {
        return Attribute::make(set: fn (?string $valor) => Identificadores::telefono($valor));
    }

    public function tipoCliente(): BelongsTo
    {
        return $this->belongsTo(TipoCliente::class);
    }

    public function motos(): HasMany
    {
        return $this->hasMany(Moto::class);
    }

    public function ordenesServicio(): HasMany
    {
        return $this->hasMany(OrdenServicio::class);
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }

    public function historialFallas(): HasMany
    {
        return $this->hasMany(HistorialFalla::class);
    }

    /**
     * Tipo que define el precio de los repuestos. Si el cliente no tiene tipo
     * (datos viejos), se usa el Regular del taller: nunca un descuento implícito.
     */
    public function tipoClienteEfectivo(): TipoCliente
    {
        return $this->tipoCliente ?? TipoCliente::regularDelTaller($this->taller_id);
    }

    /**
     * Buscador único: nombre, teléfono, cédula/NIT y placa, marca o modelo de
     * sus motos. Va dentro de un `where` agrupado para no romper los scopes
     * de taller ni el de clientes archivados.
     */
    public function scopeBuscar($query, ?string $termino)
    {
        $termino = trim((string) $termino);

        if ($termino === '') {
            return $query;
        }

        $digitos = preg_replace('/\D/', '', $termino);
        $documento = Identificadores::cedulaNit($termino);
        $placa = Identificadores::placa($termino);

        return $query->where(function ($q) use ($termino, $digitos, $documento, $placa) {
            $q->where('clientes.nombre', 'ilike', "%{$termino}%")
                ->orWhereHas('motos', function ($motos) use ($termino, $placa) {
                    $motos->where(function ($m) use ($termino, $placa) {
                        $m->where('motos.marca', 'ilike', "%{$termino}%")
                            ->orWhere('motos.modelo', 'ilike', "%{$termino}%");

                        if ($placa !== null) {
                            $m->orWhere('motos.placa', 'like', "%{$placa}%");
                        }
                    });
                });

            if ($documento !== null) {
                $q->orWhere('clientes.cedula_nit', 'like', "%{$documento}%");
            }

            // El teléfono se busca por dígitos: "300 123" encuentra "+573001234567".
            if (strlen($digitos) >= 3) {
                $q->orWhere('clientes.telefono', 'like', "%{$digitos}%");
            }
        });
    }

    public function scopeActivo($query)
    {
        return $query->where('estado_cliente', 'activo');
    }

    public function scopeHibernando($query)
    {
        return $query->where('estado_cliente', 'hibernando');
    }

    public function scopeArchivado($query)
    {
        return $query->withoutGlobalScope(ClienteActivoScope::class)
            ->where('estado_cliente', 'archivado');
    }
}
