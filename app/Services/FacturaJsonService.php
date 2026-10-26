<?php

namespace App\Services;

use App\Models\OrdenServicio;
use App\Scopes\ClienteActivoScope;
use Illuminate\Support\Facades\Storage;

/**
 * JSON de la factura en storage/app/facturas/{orden_id}.json, pensado para
 * que una IA lo lea (envío por WhatsApp, consultas, análisis).
 *
 * Regla 3: se arma campo por campo, nunca con toArray(), para que el método
 * de pago (dato interno) no pueda colarse.
 */
class FacturaJsonService
{
    public const ESQUEMA = 'servimotos.factura.v1';

    private const ZONA_HORARIA = 'America/Bogota';

    public function guardar(OrdenServicio $orden): string
    {
        $ruta = $this->ruta($orden);

        Storage::disk('facturas')->put($ruta, json_encode(
            $this->construir($orden),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        ));

        return $ruta;
    }

    /** Al corregir, la factura emitida deja de valer hasta volver a liquidar. */
    public function eliminar(OrdenServicio $orden): void
    {
        Storage::disk('facturas')->delete($this->ruta($orden));
    }

    public function ruta(OrdenServicio $orden): string
    {
        return "{$orden->id}.json";
    }

    public function construir(OrdenServicio $orden): array
    {
        $orden->loadMissing(['taller', 'moto', 'mecanico', 'detalles.repuesto']);
        $cliente = $orden->cliente()->withoutGlobalScope(ClienteActivoScope::class)->firstOrFail();
        $tipoCliente = $cliente->tipoClienteEfectivo();
        $config = $orden->taller->configuracion ?? null;

        $items = $orden->detalles->sortBy('id')->map(fn ($detalle) => [
            'tipo' => 'repuesto',
            'repuesto_id' => $detalle->repuesto_id,
            'sku' => $detalle->repuesto?->codigo_sku,
            'descripcion' => $detalle->repuesto?->nombre,
            'cantidad' => $detalle->cantidad,
            'precio_unitario_lista' => $this->pesos($detalle->precio_unitario_original),
            'descuento_pct' => (float) $detalle->descuento_aplicado_pct,
            'precio_unitario' => $this->pesos($detalle->precio_unitario),
            'subtotal' => $this->pesos($detalle->subtotal),
        ])->values()->all();

        if ((float) $orden->mano_obra > 0) {
            $items[] = [
                'tipo' => 'mano_obra',
                'descripcion' => 'Mano de obra',
                'cantidad' => 1,
                'precio_unitario' => $this->pesos($orden->mano_obra),
                'subtotal' => $this->pesos($orden->mano_obra),
            ];
        }

        $subtotalRepuestos = $this->pesos($orden->subtotal_repuestos);
        $descuento = $this->pesos($orden->descuento_total);

        return [
            'esquema' => self::ESQUEMA,
            'moneda' => 'COP',
            'numero_factura' => $orden->numero_factura,
            'orden_id' => $orden->id,
            'fecha_emision' => $orden->liquidada_at?->timezone(self::ZONA_HORARIA)->toIso8601String(),
            'fecha_ingreso_moto' => $orden->fecha_hora_ingreso?->timezone(self::ZONA_HORARIA)->toIso8601String(),
            'taller' => [
                'nombre' => $orden->taller->nombre,
                'nit' => $orden->taller->nit,
                'direccion' => $config?->direccion,
                'telefono' => $config?->telefono_contacto,
            ],
            'cliente' => [
                'nombre' => $cliente->nombre,
                'cedula_nit' => $cliente->cedula_nit,
                'telefono' => $cliente->telefono,
                'tipo_cliente' => $tipoCliente->nombre,
            ],
            'moto' => [
                'placa' => $orden->moto->placa,
                'marca' => $orden->moto->marca,
                'modelo' => $orden->moto->modelo,
                'anio' => $orden->moto->anio,
            ],
            'servicio' => [
                'falla_reportada' => $orden->descripcion_falla,
                'observaciones_tecnicas' => $orden->observaciones_tecnicas,
                'mecanico' => $orden->mecanico?->nombre,
            ],
            'items' => $items,
            'totales' => [
                // Precio de lista de los repuestos, antes del descuento por tipo de cliente.
                'repuestos_precio_lista' => $subtotalRepuestos + $descuento,
                'descuento_repuestos' => $descuento,
                'repuestos' => $subtotalRepuestos,
                'mano_obra' => $this->pesos($orden->mano_obra),
                // Repuestos con descuento + mano de obra, antes del desglose/suma de IVA.
                'subtotal' => $subtotalRepuestos + $this->pesos($orden->mano_obra),
                'base_gravable' => $this->pesos($orden->base_gravable),
                'iva' => $this->pesos($orden->iva),
                'tasa_iva_pct' => (float) $orden->tasa_iva_aplicada,
                // true: el IVA ya estaba dentro de los precios (se desglosó). false: se sumó encima.
                'precios_incluyen_iva' => $orden->precios_incluian_iva,
                'total' => $this->pesos($orden->total),
            ],
        ];
    }

    private function pesos(mixed $valor): int
    {
        return (int) round((float) $valor);
    }
}
