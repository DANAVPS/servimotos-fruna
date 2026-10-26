<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_taller', function (Blueprint $table) {
            // IVA configurable por taller (regla 6).
            $table->boolean('precios_incluyen_iva')->default(true);
            $table->boolean('discriminar_iva_en_factura')->default(true);
            $table->decimal('tasa_iva', 5, 2)->default(19.00);

            // Semáforo del Kanban, en días desde fecha_hora_ingreso.
            $table->smallInteger('dias_alerta_amarilla')->default(2);
            $table->smallInteger('dias_alerta_roja')->default(5);

            // Consecutivo de factura: se incrementa con lockForUpdate al liquidar.
            $table->unsignedInteger('ultimo_numero_factura')->default(0);
        });

        DB::statement(
            'ALTER TABLE configuracion_taller ADD CONSTRAINT configuracion_taller_tasa_iva_rango '
            . 'CHECK (tasa_iva >= 0 AND tasa_iva <= 100)'
        );

        DB::statement(
            'ALTER TABLE configuracion_taller ADD CONSTRAINT configuracion_taller_semaforo_orden '
            . 'CHECK (dias_alerta_amarilla > 0 AND dias_alerta_roja > dias_alerta_amarilla)'
        );
    }

    public function down(): void
    {
        Schema::table('configuracion_taller', function (Blueprint $table) {
            $table->dropColumn([
                'precios_incluyen_iva',
                'discriminar_iva_en_factura',
                'tasa_iva',
                'dias_alerta_amarilla',
                'dias_alerta_roja',
                'ultimo_numero_factura',
            ]);
        });
    }
};
