<?php

namespace Database\Seeders;

use App\Models\Rol;
use Illuminate\Database\Seeder;

class RolSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['nombre' => 'Super Administrador', 'slug' => Rol::SUPERADMIN],
            ['nombre' => 'Administradora', 'slug' => Rol::ADMINISTRADORA],
            ['nombre' => 'Mecánico', 'slug' => Rol::MECANICO],
        ];

        foreach ($roles as $rol) {
            Rol::firstOrCreate(['slug' => $rol['slug']], $rol);
        }
    }
}
