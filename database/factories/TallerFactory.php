<?php

namespace Database\Factories;

use App\Models\Taller;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Taller>
 */
class TallerFactory extends Factory
{
    protected $model = Taller::class;

    public function definition(): array
    {
        return [
            'nombre' => 'Taller ' . fake()->unique()->company(),
            'nit' => fake()->unique()->numerify('9########-#'),
            'telefono_whatsapp' => fake()->numerify('+57300#######'),
            'plan' => 'activo',
            'activo' => true,
        ];
    }
}
