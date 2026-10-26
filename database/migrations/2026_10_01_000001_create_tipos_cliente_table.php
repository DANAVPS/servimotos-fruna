<?php

use App\Models\TipoCliente;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_cliente', function (Blueprint $table) {
            $table->id();

            $table->foreignId('taller_id')->constrained('talleres')->onDelete('cascade');

            $table->string('nombre', 100);
            $table->string('slug', 120);
            $table->decimal('descuento_repuestos_pct', 5, 2)->default(0);
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['taller_id', 'slug']);
        });

        // Ningún descuento puede dejar un precio negativo, ni siquiera por un INSERT manual.
        DB::statement(
            'ALTER TABLE tipos_cliente ADD CONSTRAINT tipos_cliente_descuento_rango '
            . 'CHECK (descuento_repuestos_pct >= 0 AND descuento_repuestos_pct <= 100)'
        );

        // Talleres existentes: tipos base. Los nuevos los reciben de TallerObserver.
        $ahora = now();

        foreach (DB::table('talleres')->pluck('id') as $tallerId) {
            foreach (TipoCliente::BASE as $slug => $tipo) {
                DB::table('tipos_cliente')->insert([
                    'taller_id' => $tallerId,
                    'slug' => $slug,
                    'nombre' => $tipo['nombre'],
                    'descuento_repuestos_pct' => $tipo['descuento_repuestos_pct'],
                    'descripcion' => $tipo['descripcion'],
                    'activo' => true,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_cliente');
    }
};
