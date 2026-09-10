<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talleres', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('nit', 20)->nullable()->unique();
            $table->string('telefono_whatsapp', 20)->nullable();
            $table->string('whatsapp_phone_number_id')->nullable();
            $table->text('whatsapp_token')->nullable(); // encriptado vía cast
            $table->enum('plan', ['trial', 'activo', 'suspendido'])->default('trial');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talleres');
    }
};
