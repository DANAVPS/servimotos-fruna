<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citas', function (Blueprint $table) {
            $table->id();

            // Relación Multi-Tenant
            $table->foreignId('taller_id')->constrained('talleres')->onDelete('cascade');

            $table->date('fecha');
            $table->time('hora');
            $table->string('tipo_servicio');
            $table->enum('estado', ['pendiente', 'completada', 'cancelada'])
                ->default('pendiente');
            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->onDelete('cascade');
            $table->foreignId('moto_id')
                ->constrained('motos')
                ->onDelete('cascade');
            $table->timestamps();

            $table->index(['taller_id', 'fecha', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citas');
    }
};
