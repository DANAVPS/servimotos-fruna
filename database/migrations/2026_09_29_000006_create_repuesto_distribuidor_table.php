<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repuesto_distribuidor', function (Blueprint $table) {
            $table->id();

            $table->foreignId('taller_id')->constrained('talleres')->onDelete('cascade');
            $table->foreignId('repuesto_id')->constrained('repuestos')->onDelete('cascade');
            $table->foreignId('distribuidor_id')->constrained('distribuidores')->onDelete('cascade');

            $table->string('codigo_proveedor', 100)->nullable();
            $table->decimal('precio_costo_proveedor', 12, 2)->nullable();
            $table->unsignedSmallInteger('tiempo_entrega_dias')->nullable();
            $table->timestamps();

            $table->unique(['repuesto_id', 'distribuidor_id']);
            $table->index(['taller_id', 'distribuidor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repuesto_distribuidor');
    }
};
