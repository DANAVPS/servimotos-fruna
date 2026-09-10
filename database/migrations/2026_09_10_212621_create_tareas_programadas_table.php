<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tareas_programadas', function (Blueprint $table) {
            $table->id();

            // Relación Multi-Tenant
            $table->foreignId('taller_id')->constrained('talleres')->onDelete('cascade');

            $table->string('tipo');
            $table->foreignId('orden_servicio_id')
                ->nullable()
                ->constrained('ordenes_servicio')
                ->onDelete('set null');
            $table->timestamp('fecha_envio')->useCurrent();
            $table->text('respuesta_cliente')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['taller_id', 'fecha_envio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tareas_programadas');
    }
};
