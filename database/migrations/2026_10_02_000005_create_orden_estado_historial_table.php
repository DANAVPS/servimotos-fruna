<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ESTADOS = "'en_espera_revision', 'en_revision', 'reparacion', 'espera_repuesto', "
        . "'terminada', 'listo_para_reclamar', 'pagada'";

    public function up(): void
    {
        Schema::create('orden_estado_historial', function (Blueprint $table) {
            $table->id();

            $table->foreignId('taller_id')->constrained('talleres')->onDelete('cascade');
            $table->foreignId('orden_servicio_id')->constrained('ordenes_servicio')->onDelete('cascade');

            // Null solo en el evento de creación.
            $table->string('estado_anterior', 30)->nullable();
            $table->string('estado_nuevo', 30);
            $table->string('evento', 20);

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Mecánico asignado en la transición a en_revision.
            $table->foreignId('mecanico_id')->nullable()->constrained('mecanicos')->nullOnDelete();
            // En una corrección guarda el motivo (obligatorio).
            $table->text('observaciones')->nullable();

            // Bitácora inmutable: sin updated_at.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['orden_servicio_id', 'created_at']);
            $table->index(['taller_id', 'created_at']);
        });

        DB::statement(
            'ALTER TABLE orden_estado_historial ADD CONSTRAINT orden_estado_historial_estados_validos '
            . 'CHECK (estado_nuevo IN (' . self::ESTADOS . ') '
            . 'AND (estado_anterior IS NULL OR estado_anterior IN (' . self::ESTADOS . ')))'
        );

        DB::statement(
            "ALTER TABLE orden_estado_historial ADD CONSTRAINT orden_estado_historial_evento_valido "
            . "CHECK (evento IN ('creacion', 'cambio_estado', 'liquidacion', 'correccion', 'pago'))"
        );

        // Órdenes existentes: una fila de creación con su estado actual.
        DB::statement(
            'INSERT INTO orden_estado_historial (taller_id, orden_servicio_id, estado_anterior, estado_nuevo, evento, created_at) '
            . "SELECT taller_id, id, NULL, estado, 'creacion', fecha_hora_ingreso FROM ordenes_servicio"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_estado_historial');
    }
};
