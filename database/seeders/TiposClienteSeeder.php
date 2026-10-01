<?php

namespace Database\Seeders;

use App\Models\Taller;
use App\Services\TipoClienteService;
use Illuminate\Database\Seeder;

class TiposClienteSeeder extends Seeder
{
    public function run(TipoClienteService $tipos): void
    {
        // Idempotente: TallerObserver ya los crea; esto cubre talleres anteriores.
        Taller::all()->each(fn (Taller $taller) => $tipos->crearBase($taller));
    }
}
