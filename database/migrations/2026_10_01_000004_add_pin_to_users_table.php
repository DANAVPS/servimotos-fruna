<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Hash bcrypt de un PIN de 6 dígitos. Autoriza acciones sensibles
            // (editar clientes, cambiar estado, reinscribir) además de la sesión.
            $table->string('pin_autorizacion')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pin_autorizacion');
        });
    }
};
