<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_mantenimiento', function (Blueprint $table) {
            $table->id();

            // Relación Multi-Tenant
            $table->foreignId('taller_id')->constrained('talleres')->onDelete('cascade');

            $table->foreignId('orden_servicio_id')
                ->constrained('ordenes_servicio')
                ->onDelete('cascade');
            $table->foreignId('repuesto_id')
                ->constrained('repuestos')
                ->onDelete('restrict');
            $table->timestamp('fecha_cambio_repuesto');
            $table->integer('vida_util_km')->nullable();
            $table->integer('vida_util_meses')->nullable();
            $table->boolean('plantilla_whatsapp_enviada')->default(false);
            $table->timestamp('created_at')->useCurrent();

            // Índice optimizado para el Cron Job por taller
            $table->index(['taller_id', 'fecha_cambio_repuesto', 'plantilla_whatsapp_enviada'], 'idx_mantenimiento_cron');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_mantenimiento');
    }
};
