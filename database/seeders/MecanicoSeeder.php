<?php

namespace Database\Seeders;

use App\Models\Mecanico;
use App\Models\Taller;
use App\Support\TenantManager;
use Illuminate\Database\Seeder;

class MecanicoSeeder extends Seeder
{
    public function run(): void
    {
        $taller = Taller::first();

        if ($taller) {
            app(TenantManager::class)->establecer($taller->id);
        }

        Mecanico::insert([
            ['taller_id' => $taller?->id, 'nombre' => 'Alexánder Duarte Molina', 'especialidad' => 'Motor y transmisión', 'disponibilidad' => true, 'created_at' => now(), 'updated_at' => now()],
            ['taller_id' => $taller?->id, 'nombre' => 'Ferney Cárdenas Rico', 'especialidad' => 'Sistema eléctrico', 'disponibilidad' => true, 'created_at' => now(), 'updated_at' => now()],
            ['taller_id' => $taller?->id, 'nombre' => 'Yohan David Beltrán Cruz', 'especialidad' => 'Suspensión y frenos', 'disponibilidad' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
