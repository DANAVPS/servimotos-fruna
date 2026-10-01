<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Moto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Moto>
 */
class MotoFactory extends Factory
{
    protected $model = Moto::class;

    public function definition(): array
    {
        return [
            'cliente_id' => Cliente::factory(),
            'taller_id' => fn (array $atributos) => Cliente::withoutGlobalScopes()->find($atributos['cliente_id'])->taller_id,
            'placa' => fake()->unique()->regexify('[A-Z]{3}[0-9]{2}[A-Z]'),
            'marca' => 'Yamaha',
            'modelo' => 'FZ 2.0',
            'anio' => 2022,
        ];
    }
}
