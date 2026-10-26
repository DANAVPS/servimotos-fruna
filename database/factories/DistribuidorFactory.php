<?php

namespace Database\Factories;

use App\Models\Distribuidor;
use App\Models\Taller;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Distribuidor>
 */
class DistribuidorFactory extends Factory
{
    protected $model = Distribuidor::class;

    public function definition(): array
    {
        return [
            'taller_id' => Taller::factory(),
            'nombre' => 'Distribuidora ' . fake()->unique()->company(),
            'contacto' => fake()->firstName(),
            'telefono_whatsapp' => fake()->numerify('300#######'),
            'activo' => true,
        ];
    }
}
