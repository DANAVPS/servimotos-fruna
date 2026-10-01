<?php

namespace App\Services;

use App\Models\Distribuidor;
use App\Models\Repuesto;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class DistribuidorService
{
    private const CAMPOS = ['nombre', 'contacto', 'telefono_whatsapp', 'email', 'notas', 'activo'];

    public function crear(array $datos): Distribuidor
    {
        return Distribuidor::create(Arr::only($datos, self::CAMPOS));
    }

    public function actualizar(Distribuidor $distribuidor, array $datos): Distribuidor
    {
        $distribuidor->update(Arr::only($datos, self::CAMPOS));

        return $distribuidor->fresh();
    }

    /**
     * Repuestos en stock bajo que se le piden a este distribuidor. Por defecto
     * solo aquellos en los que es el preferido, para que coincida con el
     * agrupamiento de alertas del inventario y no se pida lo mismo dos veces.
     *
     * @return Collection<int, Repuesto>
     */
    public function repuestosBajos(Distribuidor $distribuidor, bool $soloPreferidos = true): Collection
    {
        $repuestos = $distribuidor->repuestos()
            ->stockBajo()
            ->with(['distribuidores', 'compatibilidades'])
            ->orderBy('nombre')
            ->get();

        if (! $soloPreferidos) {
            return $repuestos;
        }

        return $repuestos
            ->filter(fn (Repuesto $r) => $r->distribuidorPreferido()?->is($distribuidor))
            ->values();
    }
}
