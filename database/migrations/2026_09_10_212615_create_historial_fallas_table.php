<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_fallas', function (Blueprint $table) {
            $table->id();

            // Relación Multi-Tenant
            $table->foreignId('taller_id')->constrained('talleres')->onDelete('cascade');

            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->onDelete('cascade');
            $table->foreignId('moto_id')
                ->constrained('motos')
                ->onDelete('cascade');
            $table->text('sintomas')->nullable();
            $table->text('diagnostico')->nullable();
            $table->timestamp('fecha')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['taller_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_fallas');
    }
};
