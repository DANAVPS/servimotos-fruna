<?php

namespace Database\Seeders;

use App\Models\ConfiguracionTaller;
use Illuminate\Database\Seeder;

class ConfiguracionTallerSeeder extends Seeder
{
    public function run(): void
    {
        ConfiguracionTaller::firstOrCreate([], [
            'capacidad_maxima_simultanea' => 5,
            'horario_apertura' => '08:00:00',
            'horario_cierre' => '18:00:00',
        ]);
    }
}
