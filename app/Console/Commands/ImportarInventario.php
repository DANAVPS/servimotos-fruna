<?php

namespace App\Console\Commands;

use App\Models\Taller;
use App\Services\InventarioImportService;
use App\Support\Inventario\InventarioCsvParser;
use Illuminate\Console\Command;

class ImportarInventario extends Command
{
    protected $signature = 'inventario:importar
        {archivo? : Ruta del CSV (por defecto database/seeders/data/inventario.csv)}
        {--taller= : ID del taller destino (por defecto el primero)}
        {--stock-minimo=1 : Stock mínimo para filas que no lo traen}
        {--correcciones= : Archivo PHP de correcciones (por defecto el del CSV incluido)}
        {--simular : Solo lee el archivo y genera el reporte, sin tocar la base}
        {--reporte= : Ruta donde guardar el reporte CSV de revisión}';

    protected $description = 'Importa la plantilla de inventario (Excel exportado a CSV) a un taller';

    public function handle(InventarioImportService $importador): int
    {
        $ruta = $this->argument('archivo') ?? database_path('seeders/data/inventario.csv');
        $stockMinimo = (int) $this->option('stock-minimo');
        $correcciones = $this->correcciones();

        if ($this->option('simular')) {
            $parser = new InventarioCsvParser($stockMinimo);
            $resultado = $parser->aplicarCorrecciones($parser->parsearArchivo($ruta), $correcciones);
            $this->info(count($resultado['filas']) . ' filas válidas (simulación, no se guardó nada).');
        } else {
            $taller = $this->option('taller')
                ? Taller::findOrFail($this->option('taller'))
                : Taller::orderBy('id')->firstOrFail();

            $resultado = $importador->importar($ruta, $taller, $stockMinimo, $correcciones);
            $this->info("Taller \"{$taller->nombre}\": {$resultado['creados']} repuestos creados, "
                . "{$resultado['existentes']} ya existían.");
        }

        foreach ($resultado['omitidas'] as $omitida) {
            $this->line("  Línea {$omitida['linea']} omitida: {$omitida['motivo']}");
        }

        $conAvisos = array_filter($resultado['filas'], fn ($fila) => $fila['avisos'] !== []);
        $bajos = array_filter($resultado['filas'], fn ($fila) => $fila['stock_actual'] <= $fila['stock_minimo']);

        $this->line(count($conAvisos) . ' filas con avisos para revisar; '
            . count($bajos) . ' quedarán en stock bajo.');

        if ($reporte = $this->option('reporte')) {
            $this->escribirReporte($reporte, $resultado['filas']);
            $this->info("Reporte de revisión: {$reporte}");
        }

        return self::SUCCESS;
    }

    /**
     * Las correcciones por defecto solo aplican al CSV incluido en el proyecto.
     */
    private function correcciones(): array
    {
        $ruta = $this->option('correcciones')
            ?? ($this->argument('archivo') === null ? database_path('seeders/data/inventario_correcciones.php') : null);

        return $ruta !== null && is_file($ruta) ? require $ruta : [];
    }

    /**
     * @param array<int, array<string, mixed>> $filas
     */
    private function escribirReporte(string $ruta, array $filas): void
    {
        $archivo = fopen($ruta, 'w');
        fwrite($archivo, "\xEF\xBB\xBF"); // BOM para que Excel abra el UTF-8 con tildes

        fputcsv($archivo, [
            'Línea Excel', 'SKU', 'Referencia proveedor', 'Nombre', 'Categoría', 'Compatibilidad',
            'Ubicación', 'Stock', 'Stock mínimo', 'Costo', 'Costo calculado (60%)', 'Precio venta', 'Avisos',
        ], ';', '"', '');

        foreach ($filas as $fila) {
            fputcsv($archivo, [
                $fila['linea'],
                $fila['codigo_sku'],
                $fila['referencia_proveedor'],
                $fila['nombre'],
                $fila['categoria_slug'],
                implode(' / ', $fila['compatibilidades']),
                $fila['ubicacion_estante'],
                $fila['stock_actual'],
                $fila['stock_minimo'],
                $fila['precio_costo'],
                $fila['costo_calculado'] ? 'sí' : 'no',
                $fila['precio_venta'],
                implode(' | ', $fila['avisos']),
            ], ';', '"', '');
        }

        fclose($archivo);
    }
}
