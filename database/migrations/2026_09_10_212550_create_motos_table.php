<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motos', function (Blueprint $table) {
            $table->id();

            // Relación Multi-Tenant y Cliente
            $table->foreignId('taller_id')->constrained('talleres')->onDelete('cascade');
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');

            $table->string('placa', 10);
            $table->string('marca');
            $table->string('modelo');
            $table->year('anio')->nullable();
            $table->timestamps();

            // La placa es única únicamente dentro del mismo taller
            $table->unique(['taller_id', 'placa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motos');
    }
};
