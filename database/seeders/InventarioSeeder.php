<?php

namespace Database\Seeders;

use App\Models\Taller;
use App\Services\InventarioImportService;
use Illuminate\Database\Seeder;

/**
 * Carga el inventario histórico del Excel (database/seeders/data/inventario.csv)
 * en el taller principal. Reemplaza al RepuestoSeeder de ejemplo.
 */
class InventarioSeeder extends Seeder
{
    public function run(InventarioImportService $importador): void
    {
        $taller = Taller::orderBy('id')->first();

        if (! $taller) {
            return;
        }

        $resultado = $importador->importar(
            database_path('seeders/data/inventario.csv'),
            $taller,
            correcciones: require database_path('seeders/data/inventario_correcciones.php'),
        );

        $this->command?->info(
            "Inventario: {$resultado['creados']} repuestos creados, {$resultado['existentes']} ya existían, "
            . count($resultado['omitidas']) . ' filas omitidas.'
        );
    }
}
