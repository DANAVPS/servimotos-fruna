<?php

namespace Database\Factories;

use App\Models\Repuesto;
use App\Models\Taller;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Repuesto>
 */
class RepuestoFactory extends Factory
{
    protected $model = Repuesto::class;

    public function definition(): array
    {
        $precioVenta = fake()->numberBetween(5, 200) * 1000;

        return [
            'taller_id' => Taller::factory(),
            'nombre' => 'Repuesto ' . fake()->unique()->word(),
            'precio_costo' => $precioVenta * 0.6,
            'precio_venta' => $precioVenta,
            'stock_actual' => 10,
            'stock_minimo' => 2,
        ];
    }

    public function stockBajo(int $actual = 1, int $minimo = 3): static
    {
        return $this->state(fn () => ['stock_actual' => $actual, 'stock_minimo' => $minimo]);
    }
}
