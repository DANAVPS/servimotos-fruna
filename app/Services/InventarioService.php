<?php

namespace App\Services;

use App\Models\Repuesto;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventarioService
{
    /**
     * Descuenta stock con bloqueo pesimista para evitar condiciones de
     * carrera cuando varias órdenes liquidan el mismo repuesto a la vez.
     */
    public function descontarStock(int $repuestoId, int $cantidad): Repuesto
    {
        return DB::transaction(function () use ($repuestoId, $cantidad) {
            $repuesto = Repuesto::lockForUpdate()->findOrFail($repuestoId);

            if ($repuesto->stock_actual < $cantidad) {
                throw new RuntimeException("Stock insuficiente para {$repuesto->nombre}.");
            }

            $repuesto->decrement('stock_actual', $cantidad);

            return $repuesto->fresh();
        });
    }

    /**
     * Repuestos que requieren alerta: el proveedor tarda de 2 a 5 días
     * hábiles, así que alertamos cuando el stock alcanza para menos de
     * 5 días de consumo promedio (regla 2.5 del prompt base).
     */
    public function repuestosConAlerta()
    {
        return Repuesto::stockBajo()->get();
    }
}
