<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNAS = [
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

    public function up(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            // Consecutivo por taller, asignado en backend al liquidar. Una
            // corrección conserva el número (la factura se reemite).
            $table->unsignedInteger('numero_factura')->nullable();

            // Mano de obra única por orden (antes era por línea de detalle).
            $table->decimal('mano_obra', 12, 2)->default(0);
            // Obligatorias al liquidar (regla 9), se valida en LiquidarOrdenRequest.
            $table->text('observaciones_tecnicas')->nullable();
            $table->timestamp('liquidada_at')->nullable();

            // Totales congelados al liquidar: cambiar el IVA del taller
            // después no altera facturas emitidas (misma idea que la regla 7).
            $table->decimal('subtotal_repuestos', 12, 2)->default(0);
            $table->decimal('descuento_total', 12, 2)->default(0);
            $table->decimal('base_gravable', 12, 2)->default(0);
            $table->decimal('iva', 12, 2)->default(0);
            $table->decimal('tasa_iva_aplicada', 5, 2)->default(0);
            $table->boolean('precios_incluian_iva')->default(true);

            // Dato interno (regla 3): nunca va al PDF, vista ni JSON del cliente.
            // Vacío en órdenes pagadas antes del Bloque 3 → "Sin registrar".
            $table->string('metodo_pago', 20)->nullable();
            $table->timestamp('pagada_at')->nullable();

            $table->unique(['taller_id', 'numero_factura']);
            $table->index(['taller_id', 'pagada_at']);
        });

        DB::statement(
            "ALTER TABLE ordenes_servicio ADD CONSTRAINT ordenes_servicio_metodo_pago_valido "
            . "CHECK (metodo_pago IS NULL OR metodo_pago IN ('efectivo', 'transferencia', 'nequi', 'daviplata'))"
        );

        // La mano de obra por línea pasa a la orden (detalles_orden.mano_obra
        // se elimina en la siguiente migración). Los totales congelados de
        // órdenes ya liquidadas quedan en 0: son datos de prueba.
        DB::statement(
            'UPDATE ordenes_servicio o SET mano_obra = d.total '
            . 'FROM (SELECT orden_servicio_id, SUM(mano_obra) AS total FROM detalles_orden GROUP BY orden_servicio_id) d '
            . 'WHERE d.orden_servicio_id = o.id'
        );
    }

    public function down(): void
    {
        // El índice, la restricción única y el CHECK caen con sus columnas.
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNAS);
        });
    }
};
