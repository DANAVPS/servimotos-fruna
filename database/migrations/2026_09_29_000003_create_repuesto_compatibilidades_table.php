<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La compatibilidad es texto ("Duke 200"), no FK a `motos`: esa tabla
        // guarda las motos de los clientes, no un catálogo de modelos.
        Schema::create('repuesto_compatibilidades', function (Blueprint $table) {
            $table->id();

            $table->foreignId('taller_id')->constrained('talleres')->onDelete('cascade');
            $table->foreignId('repuesto_id')->constrained('repuestos')->onDelete('cascade');

            $table->string('moto_referencia', 100);
            $table->string('moto_referencia_normalizada', 100);
            $table->timestamps();

            $table->unique(['repuesto_id', 'moto_referencia_normalizada']);
            $table->index(['taller_id', 'moto_referencia_normalizada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repuesto_compatibilidades');
    }
};
