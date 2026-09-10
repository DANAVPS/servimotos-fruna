<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_taller', function (Blueprint $table) {
            $table->id();

            // Configuración 1 a 1 por taller
            $table->foreignId('taller_id')->unique()->constrained('talleres')->onDelete('cascade');

            $table->integer('capacidad_maxima_simultanea')->default(5);
            $table->time('horario_apertura')->default('08:00:00');
            $table->time('horario_cierre')->default('18:00:00');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_taller');
    }
};
