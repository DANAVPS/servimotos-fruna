<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Taller;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    protected $model = Cliente::class;

    public function definition(): array
    {
        return [
            'taller_id' => Taller::factory(),
            'nombre' => fake()->name(),
            'telefono' => fake()->unique()->numerify('300#######'),
            'estado_cliente' => 'activo',
            'fecha_ultima_visita' => now(),
        ];
    }
}
