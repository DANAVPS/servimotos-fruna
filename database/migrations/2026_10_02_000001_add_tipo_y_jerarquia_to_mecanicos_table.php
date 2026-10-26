<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // `disponibilidad` (disponible hoy) se conserva; `activo` = sigue en el taller.
        Schema::table('mecanicos', function (Blueprint $table) {
            $table->string('telefono', 20)->nullable();
            $table->string('tipo', 10)->default('titular');
            $table->foreignId('mecanico_principal_id')
                ->nullable()
                ->constrained('mecanicos')
                ->nullOnDelete();
            $table->boolean('activo')->default(true);
        });

        DB::statement(
            "ALTER TABLE mecanicos ADD CONSTRAINT mecanicos_tipo_valido "
            . "CHECK (tipo IN ('titular', 'ayudante'))"
        );

        // Un titular no depende de nadie. Que el principal sea titular y del
        // mismo taller lo valida el Form Request (un CHECK no ve otras filas).
        DB::statement(
            "ALTER TABLE mecanicos ADD CONSTRAINT mecanicos_titular_sin_principal "
            . "CHECK (tipo = 'ayudante' OR mecanico_principal_id IS NULL)"
        );

        DB::statement(
            'ALTER TABLE mecanicos ADD CONSTRAINT mecanicos_principal_no_propio '
            . 'CHECK (mecanico_principal_id IS NULL OR mecanico_principal_id <> id)'
        );
    }

    public function down(): void
    {
        Schema::table('mecanicos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mecanico_principal_id');
        });

        // Los CHECK sobre `tipo` caen con la columna.
        Schema::table('mecanicos', function (Blueprint $table) {
            $table->dropColumn(['telefono', 'tipo', 'activo']);
        });
    }
};
