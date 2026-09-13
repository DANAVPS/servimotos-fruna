<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
public function run(): void
{
    $this->call([
        RolSeeder::class,
        TallerSeeder::class,
        UserSeeder::class,
        ConfiguracionTallerSeeder::class,
        ClienteSeeder::class,
        MecanicoSeeder::class,
        RepuestoSeeder::class,
        MotoSeeder::class,
        OrdenServicioSeeder::class,
    ]);
}
}
