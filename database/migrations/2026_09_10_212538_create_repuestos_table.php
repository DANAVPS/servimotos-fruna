<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repuestos', function (Blueprint $table) {
            $table->id();

            // Relación Multi-Tenant
            $table->foreignId('taller_id')->constrained('talleres')->onDelete('cascade');

            $table->string('nombre');
            $table->string('marca')->nullable();
            $table->string('modelo_compatible')->nullable();
            $table->decimal('precio_costo', 12, 2)->default(0);
            $table->decimal('precio_venta', 12, 2)->default(0);
            $table->integer('stock_actual')->default(0);
            $table->integer('stock_minimo')->default(0);
            $table->timestamps();

            $table->index(['taller_id', 'stock_actual']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repuestos');
    }
};
