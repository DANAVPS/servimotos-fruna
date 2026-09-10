<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Moto;
use Illuminate\Database\Seeder;

class MotoSeeder extends Seeder
{
    public function run(): void
    {
        $motosPorPlaca = [
            ['placa' => 'ABC12D', 'marca' => 'Yamaha', 'modelo' => 'FZ 150', 'anio' => 2021],
            ['placa' => 'XYZ34F', 'marca' => 'AKT', 'modelo' => 'NKD 125', 'anio' => 2020],
            ['placa' => 'JKL56M', 'marca' => 'Honda', 'modelo' => 'CB 190R', 'anio' => 2022],
            ['placa' => 'QWE78P', 'marca' => 'Bajaj', 'modelo' => 'Boxer CT100', 'anio' => 2019],
            ['placa' => 'RTY90S', 'marca' => 'Suzuki', 'modelo' => 'GN 125', 'anio' => 2018],
            ['placa' => 'UIO23D', 'marca' => 'Yamaha', 'modelo' => 'XTZ 125', 'anio' => 2023],
            ['placa' => 'ASD45G', 'marca' => 'AKT', 'modelo' => 'AK 125', 'anio' => 2021],
            ['placa' => 'FGH67J', 'marca' => 'Honda', 'modelo' => 'Discover 125', 'anio' => 2020],
        ];

        $clientes = Cliente::withoutGlobalScopes()->get();

        foreach ($motosPorPlaca as $index => $data) {
            $cliente = $clientes[$index % $clientes->count()];

            Moto::create([
                ...$data,
                'cliente_id' => $cliente->id,
            ]);
        }
    }
}
