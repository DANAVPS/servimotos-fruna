<?php

use App\Models\TipoCliente;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->foreignId('tipo_cliente_id')
                ->nullable()
                ->after('taller_id')
                ->constrained('tipos_cliente')
                ->nullOnDelete();

            // Cédula, NIT (con dígito de verificación) o documento extranjero, normalizado.
            $table->string('cedula_nit', 20)->nullable()->after('nombre');
        });

        // Única por taller, pero opcional: varios clientes pueden no tenerla.
        DB::statement(
            'CREATE UNIQUE INDEX clientes_taller_cedula_nit_unique ON clientes (taller_id, cedula_nit) '
            . 'WHERE cedula_nit IS NOT NULL'
        );

        // Clientes existentes quedan como Regular de su taller.
        DB::statement(
            'UPDATE clientes SET tipo_cliente_id = tc.id FROM tipos_cliente tc '
            . 'WHERE tc.taller_id = clientes.taller_id AND tc.slug = ? AND clientes.tipo_cliente_id IS NULL',
            [TipoCliente::SLUG_REGULAR]
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS clientes_taller_cedula_nit_unique');

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tipo_cliente_id');
            $table->dropColumn('cedula_nit');
        });
    }
};
