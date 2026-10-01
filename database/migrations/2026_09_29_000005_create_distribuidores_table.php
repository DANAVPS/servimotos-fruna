<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distribuidores', function (Blueprint $table) {
            $table->id();

            $table->foreignId('taller_id')->constrained('talleres')->onDelete('cascade');

            $table->string('nombre', 150);
            $table->string('contacto', 150)->nullable();
            $table->string('telefono_whatsapp', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('notas')->nullable();
            $table->boolean('activo')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['taller_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribuidores');
    }
};
