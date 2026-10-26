<?php

namespace Database\Seeders;

use App\Models\Taller;
use App\Services\CategoriaRepuestoService;
use Illuminate\Database\Seeder;

class CategoriasRepuestoSeeder extends Seeder
{
    public function run(CategoriaRepuestoService $categorias): void
    {
        // Idempotente: TallerObserver ya las crea al registrar el taller;
        // esto cubre talleres creados antes del observer.
        Taller::all()->each(fn (Taller $taller) => $categorias->crearBase($taller));
    }
}
