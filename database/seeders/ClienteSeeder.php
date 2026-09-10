<?php

namespace Database\Seeders;

use App\Models\Cliente;
use Illuminate\Database\Seeder;

class ClienteSeeder extends Seeder
{
    public function run(): void
    {
        $clientes = [
            ['nombre' => 'Carlos Andrés Pérez Gómez', 'telefono' => '+573001234567', 'estado_cliente' => 'activo'],
            ['nombre' => 'María Fernanda Rodríguez Suárez', 'telefono' => '+573109876543', 'estado_cliente' => 'activo'],
            ['nombre' => 'Jorge Enrique Martínez Díaz', 'telefono' => '+573201122334', 'estado_cliente' => 'activo'],
            ['nombre' => 'Luz Adriana Vargas Torres', 'telefono' => '+573156677889', 'estado_cliente' => 'hibernando'],
            ['nombre' => 'Wilson Alberto Ramírez Castro', 'telefono' => '+573012345678', 'estado_cliente' => 'activo'],
            ['nombre' => 'Yesenia Patricia López Mendoza', 'telefono' => '+573187654321', 'estado_cliente' => 'activo'],
            ['nombre' => 'Édgar Fabián Sánchez Ortiz', 'telefono' => '+573045566778', 'estado_cliente' => 'hibernando'],
            ['nombre' => 'Diana Carolina Herrera Peña', 'telefono' => '+573223344556', 'estado_cliente' => 'activo'],
        ];

        foreach ($clientes as $data) {
            Cliente::create([
                ...$data,
                'correo' => null,
                'fecha_ultima_visita' => $data['estado_cliente'] === 'activo'
                    ? now()->subDays(rand(1, 60))
                    : now()->subMonths(rand(6, 8)),
            ]);
        }
    }
}
