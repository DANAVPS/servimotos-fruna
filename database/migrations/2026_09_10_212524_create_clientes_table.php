<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();

            // Relación con el taller (Multi-Tenant)
            $table->foreignId('taller_id')->constrained('talleres')->onDelete('cascade');

            $table->string('nombre');
            $table->string('telefono', 20);
            $table->string('correo')->nullable();
            $table->timestamp('fecha_ultima_visita')->nullable();
            $table->enum('estado_cliente', ['activo', 'hibernando', 'archivado'])
                ->default('activo');
            $table->timestamps();

            // Clave única compuesta: un mismo teléfono no se repite dentro del mismo taller
            $table->unique(['taller_id', 'telefono']);
            $table->index('estado_cliente');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
