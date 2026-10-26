<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registro inmutable de cada acción autorizada con PIN: quién, qué y cuándo.
     * Solo created_at: un registro de auditoría no se edita.
     */
    public function up(): void
    {
        Schema::create('auditoria_autorizaciones', function (Blueprint $table) {
            $table->id();

            // Nullable: un SuperAdmin puede actuar sobre un registro sin taller propio.
            $table->foreignId('taller_id')->nullable()->constrained('talleres')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->string('accion', 100);
            $table->string('modelo_afectado', 100);
            $table->unsignedBigInteger('modelo_id');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['taller_id', 'created_at']);
            $table->index(['modelo_afectado', 'modelo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria_autorizaciones');
    }
};
