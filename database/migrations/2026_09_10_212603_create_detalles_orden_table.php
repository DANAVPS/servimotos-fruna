<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalles_orden', function (Blueprint $table) {
            $table->id();

            $table->foreignId('taller_id')->constrained('talleres')->onDelete('cascade');
            
            $table->foreignId('orden_servicio_id')
                ->constrained('ordenes_servicio')
                ->onDelete('cascade');
            $table->foreignId('repuesto_id')
                ->constrained('repuestos')
                ->onDelete('restrict');
            $table->integer('cantidad')->default(1);
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('mano_obra', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalles_orden');
    }
};
