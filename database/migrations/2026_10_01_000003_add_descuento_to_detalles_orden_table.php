<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot del precio al liquidar: `precio_unitario` es el precio final con
     * descuento; aquí queda el precio de lista y el % aplicado, para auditoría
     * y para que un cambio posterior del tipo de cliente no altere facturas viejas.
     */
    public function up(): void
    {
        Schema::table('detalles_orden', function (Blueprint $table) {
            $table->decimal('precio_unitario_original', 12, 2)->nullable()->after('precio_unitario');
            $table->decimal('descuento_aplicado_pct', 5, 2)->default(0)->after('precio_unitario_original');
        });

        // Detalles anteriores no tenían descuento: el precio original es el cobrado.
        DB::table('detalles_orden')->update(['precio_unitario_original' => DB::raw('precio_unitario')]);

        Schema::table('detalles_orden', function (Blueprint $table) {
            $table->decimal('precio_unitario_original', 12, 2)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('detalles_orden', function (Blueprint $table) {
            $table->dropColumn(['precio_unitario_original', 'descuento_aplicado_pct']);
        });
    }
};
