<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordenes_servicio', function (Blueprint $table) {
            $table->id();

            // Relación Multi-Tenant
            $table->foreignId('taller_id')->constrained('talleres')->onDelete('cascade');

            $table->timestamp('fecha_hora_ingreso')->useCurrent();
            $table->timestamp('ultima_interaccion_cliente')->nullable();
            $table->enum('estado', [
                'en_espera_revision',
                'en_revision',
                'reparacion',
                'espera_repuesto',
                'terminada',
                'listo_para_reclamar',
                'pagada',
            ])->default('en_espera_revision');

            $table->text('descripcion_falla')->nullable();
            $table->foreignId('mecanico_id')
                ->nullable()
                ->constrained('mecanicos')
                ->onDelete('set null');
            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->onDelete('restrict');
            $table->foreignId('moto_id')
                ->constrained('motos')
                ->onDelete('restrict');
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();

            // Índices para velocidad de consulta en Kanban e historial
            $table->index(['taller_id', 'estado']);
            $table->index('fecha_hora_ingreso');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes_servicio');
    }
};
