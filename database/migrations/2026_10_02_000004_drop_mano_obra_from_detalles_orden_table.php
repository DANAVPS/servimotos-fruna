<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ya copiada a ordenes_servicio.mano_obra por la migración 000003.
        Schema::table('detalles_orden', function (Blueprint $table) {
            $table->dropColumn('mano_obra');
        });

        DB::statement(
            'ALTER TABLE detalles_orden ADD CONSTRAINT detalles_orden_cantidad_positiva CHECK (cantidad > 0)'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE detalles_orden DROP CONSTRAINT detalles_orden_cantidad_positiva');

        Schema::table('detalles_orden', function (Blueprint $table) {
            $table->decimal('mano_obra', 12, 2)->default(0);
        });

        // Aproximado: la mano de obra de la orden vuelve completa a su primer detalle.
        DB::statement(
            'UPDATE detalles_orden d SET mano_obra = o.mano_obra '
            . 'FROM ordenes_servicio o '
            . 'WHERE o.id = d.orden_servicio_id '
            . 'AND d.id = (SELECT MIN(id) FROM detalles_orden WHERE orden_servicio_id = o.id)'
        );
    }
};
