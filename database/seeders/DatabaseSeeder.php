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
        CategoriasRepuestoSeeder::class,
        TiposClienteSeeder::class,
        ClienteSeeder::class,
        MecanicoSeeder::class,
        InventarioSeeder::class,
        DistribuidorSeeder::class,
        MotoSeeder::class,
        OrdenServicioSeeder::class,
    ]);
}
}
