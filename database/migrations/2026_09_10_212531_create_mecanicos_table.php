<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mecanicos', function (Blueprint $table) {
            $table->id();

            // Relación con el taller (Multi-Tenant)
            $table->foreignId('taller_id')->constrained('talleres')->onDelete('cascade');

            // Relación con la cuenta de usuario iniciada en el sistema
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->onDelete('set null');

            $table->string('nombre');
            $table->string('especialidad')->nullable();
            $table->boolean('disponibilidad')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mecanicos');
    }
};
