<?php

namespace App\Services;

use App\Models\DetalleOrden;
use App\Models\OrdenServicio;
use Illuminate\Support\Facades\DB;

class FacturaService
{
    private const TASA_IVA = 0.19;

    public function __construct(private InventarioService $inventarioService)
    {
    }

    /**
     * Liquida la orden: registra los repuestos usados, descuenta stock,
     * calcula IVA 19% y actualiza el total de la orden.
     *
     * @param array<int, array{repuesto_id: int, cantidad: int, mano_obra: float}> $items
     */
    public function liquidar(OrdenServicio $orden, array $items): OrdenServicio
    {
        return DB::transaction(function () use ($orden, $items) {
            $orden->detalles()->delete();

            foreach ($items as $item) {
                $repuesto = $this->inventarioService->descontarStock(
                    $item['repuesto_id'],
                    $item['cantidad']
                );

                DetalleOrden::create([
                    'orden_servicio_id' => $orden->id,
                    'repuesto_id' => $repuesto->id,
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $repuesto->precio_venta,
                    'subtotal' => $repuesto->precio_venta * $item['cantidad'],
                    'mano_obra' => $item['mano_obra'] ?? 0,
                ]);
            }

            $totales = $this->calcularTotales($orden->fresh('detalles'));

            $orden->update([
                'total' => $totales['total'],
                'estado' => 'listo_para_reclamar',
            ]);

            return $orden->fresh(['detalles.repuesto', 'cliente', 'moto']);
        });
    }

    /**
     * @return array{subtotal: float, iva: float, total: float}
     */
    public function calcularTotales(OrdenServicio $orden): array
    {
        $subtotal = $orden->detalles->sum(fn ($detalle) => $detalle->subtotal + $detalle->mano_obra);
        $iva = round($subtotal * self::TASA_IVA, 2);

        return [
            'subtotal' => round($subtotal, 2),
            'iva' => $iva,
            'total' => round($subtotal + $iva, 2),
        ];
    }
}
