<?php

use App\Models\CategoriaRepuesto;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla (no enum): cada taller define y renombra sus propias secciones
        // sin requerir migraciones, y un enum de Postgres no admite borrar valores.
        Schema::create('categorias_repuesto', function (Blueprint $table) {
            $table->id();

            $table->foreignId('taller_id')->constrained('talleres')->onDelete('cascade');

            $table->string('nombre', 100);
            $table->string('slug', 120);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['taller_id', 'slug']);
        });

        // Los talleres existentes reciben las categorías base; los nuevos
        // las reciben desde TallerObserver.
        $ahora = now();

        foreach (DB::table('talleres')->pluck('id') as $tallerId) {
            $filas = [];
            $orden = 1;

            foreach (CategoriaRepuesto::BASE as $slug => $nombre) {
                $filas[] = [
                    'taller_id' => $tallerId,
                    'nombre' => $nombre,
                    'slug' => $slug,
                    'orden' => $orden++,
                    'activo' => true,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }

            DB::table('categorias_repuesto')->insert($filas);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias_repuesto');
    }
};
