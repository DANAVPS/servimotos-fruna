<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repuestos', function (Blueprint $table) {
            $table->foreignId('categoria_repuesto_id')
                ->nullable()
                ->after('taller_id')
                ->constrained('categorias_repuesto')
                ->nullOnDelete();

            // SKU = código interno del taller; referencia_proveedor = el código
            // con el que el distribuidor identifica la pieza al hacer pedidos.
            $table->string('codigo_sku', 50)->nullable()->after('categoria_repuesto_id');
            $table->string('referencia_proveedor', 100)->nullable()->after('codigo_sku');
            $table->string('ubicacion_estante', 100)->nullable()->after('stock_minimo');

            // Índice de búsqueda normalizado (nombre, marca, SKU, referencia y
            // motos compatibles). Lo mantiene el modelo Repuesto.
            $table->text('busqueda')->nullable();

            // Borrado lógico: las facturas históricas siguen mostrando el repuesto.
            $table->softDeletes();

            $table->index(['taller_id', 'categoria_repuesto_id']);
        });

        // SKU único por taller solo entre repuestos vigentes, para que un SKU
        // de un repuesto eliminado se pueda reutilizar.
        DB::statement(
            'CREATE UNIQUE INDEX repuestos_taller_sku_unique ON repuestos (taller_id, codigo_sku) '
            . 'WHERE deleted_at IS NULL AND codigo_sku IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS repuestos_taller_sku_unique');

        Schema::table('repuestos', function (Blueprint $table) {
            $table->dropIndex(['taller_id', 'categoria_repuesto_id']);
            $table->dropConstrainedForeignId('categoria_repuesto_id');
            $table->dropColumn(['codigo_sku', 'referencia_proveedor', 'ubicacion_estante', 'busqueda', 'deleted_at']);
        });
    }
};
