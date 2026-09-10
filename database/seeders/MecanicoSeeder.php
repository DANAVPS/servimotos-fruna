<?php

namespace Database\Seeders;

use App\Models\Mecanico;
use Illuminate\Database\Seeder;

class MecanicoSeeder extends Seeder
{
    public function run(): void
    {
        Mecanico::insert([
            ['nombre' => 'Alexánder Duarte Molina', 'especialidad' => 'Motor y transmisión', 'disponibilidad' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Ferney Cárdenas Rico', 'especialidad' => 'Sistema eléctrico', 'disponibilidad' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Yohan David Beltrán Cruz', 'especialidad' => 'Suspensión y frenos', 'disponibilidad' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
