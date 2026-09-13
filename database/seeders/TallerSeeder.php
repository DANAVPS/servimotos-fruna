<?php

namespace Database\Seeders;

use App\Models\Taller;
use Illuminate\Database\Seeder;

class TallerSeeder extends Seeder
{
    public function run(): void
    {
        Taller::firstOrCreate(
            ['nit' => '900123456-1'],
            [
                'nombre' => 'Servimotos Fruna',
                'telefono_whatsapp' => '+573001112233',
                'plan' => 'activo',
                'activo' => true,
            ]
        );
    }
}
