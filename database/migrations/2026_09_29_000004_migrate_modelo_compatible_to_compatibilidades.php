<?php

use App\Support\Inventario\CompatibilidadParser;
use App\Support\TextoBusqueda;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Pasa el texto libre de `modelo_compatible` a la tabla de compatibilidades
     * y construye el índice de búsqueda de los repuestos existentes.
     *
     * `modelo_compatible` se conserva como texto de presentación: a partir de
     * ahora lo escribe solo InventarioService, derivado de la tabla.
     */
    public function up(): void
    {
        $ahora = now();

        DB::table('repuestos')->orderBy('id')->each(function ($repuesto) use ($ahora) {
            $referencias = CompatibilidadParser::parsear($repuesto->modelo_compatible);

            foreach ($referencias as $referencia) {
                DB::table('repuesto_compatibilidades')->insert([
                    'taller_id' => $repuesto->taller_id,
                    'repuesto_id' => $repuesto->id,
                    'moto_referencia' => $referencia,
                    'moto_referencia_normalizada' => TextoBusqueda::normalizar($referencia),
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }

            DB::table('repuestos')->where('id', $repuesto->id)->update([
                'busqueda' => TextoBusqueda::indice([
                    $repuesto->nombre,
                    $repuesto->marca,
                    ...$referencias,
                ]),
            ]);
        });
    }

    public function down(): void
    {
        // La tabla de compatibilidades se elimina en su propia migración.
    }
};
