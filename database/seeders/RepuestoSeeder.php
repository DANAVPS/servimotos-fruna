<?php

namespace Database\Seeders;

use App\Models\Repuesto;
use Illuminate\Database\Seeder;

class RepuestoSeeder extends Seeder
{
    public function run(): void
    {
        $repuestos = [
            ['nombre' => 'Filtro de aceite', 'marca' => 'Yamaha', 'modelo_compatible' => 'FZ 150 / XTZ 125', 'precio_costo' => 12000, 'precio_venta' => 22000, 'stock_actual' => 15, 'stock_minimo' => 5],
            ['nombre' => 'Pastillas de freno delanteras', 'marca' => 'AKT', 'modelo_compatible' => 'NKD 125 / AK 125', 'precio_costo' => 18000, 'precio_venta' => 35000, 'stock_actual' => 3, 'stock_minimo' => 6],
            ['nombre' => 'Cadena de transmisión', 'marca' => 'DID', 'modelo_compatible' => 'Universal 428H', 'precio_costo' => 45000, 'precio_venta' => 80000, 'stock_actual' => 8, 'stock_minimo' => 4],
            ['nombre' => 'Bujía NGK', 'marca' => 'NGK', 'modelo_compatible' => 'Universal', 'precio_costo' => 6000, 'precio_venta' => 13000, 'stock_actual' => 2, 'stock_minimo' => 10],
            ['nombre' => 'Aceite 20W-50 (1L)', 'marca' => 'Motul', 'modelo_compatible' => 'Universal 4T', 'precio_costo' => 15000, 'precio_venta' => 28000, 'stock_actual' => 25, 'stock_minimo' => 10],
            ['nombre' => 'Llanta trasera 90/90-18', 'marca' => 'Michelin', 'modelo_compatible' => 'Boxer / Discover 125', 'precio_costo' => 95000, 'precio_venta' => 160000, 'stock_actual' => 4, 'stock_minimo' => 3],
        ];

        foreach ($repuestos as $repuesto) {
            Repuesto::create($repuesto);
        }
    }
}
