<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Datos que aparecen en documentos impresos: orden de compra a
        // distribuidores (Bloque 1) y factura al cliente (Bloque 3).
        Schema::table('configuracion_taller', function (Blueprint $table) {
            $table->string('direccion')->nullable();
            $table->string('telefono_contacto', 30)->nullable();
            $table->string('email_contacto')->nullable();
            $table->string('logo_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_taller', function (Blueprint $table) {
            $table->dropColumn(['direccion', 'telefono_contacto', 'email_contacto', 'logo_path']);
        });
    }
};
