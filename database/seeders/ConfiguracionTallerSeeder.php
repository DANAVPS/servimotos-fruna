<?php

namespace Database\Seeders;

use App\Models\ConfiguracionTaller;
use App\Models\Taller;
use App\Support\TenantManager;
use Illuminate\Database\Seeder;

class ConfiguracionTallerSeeder extends Seeder
{
    public function run(): void
    {
        $taller = Taller::first();

        if ($taller) {
            app(TenantManager::class)->establecer($taller->id);
        }

        ConfiguracionTaller::firstOrCreate(
            ['taller_id' => $taller?->id],
            [
                'capacidad_maxima_simultanea' => 5,
                'horario_apertura' => '08:00:00',
                'horario_cierre' => '18:00:00',
            ]
        );
    }
}
