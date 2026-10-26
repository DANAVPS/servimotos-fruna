<?php

namespace App\Services;

use App\Models\CategoriaRepuesto;
use App\Models\Taller;

class CategoriaRepuestoService
{
    /**
     * Crea las categorías base que le falten al taller. Es idempotente:
     * no toca las que ya existen (el taller pudo haberlas renombrado).
     */
    public function crearBase(Taller $taller): void
    {
        $existentes = CategoriaRepuesto::withoutGlobalScopes()
            ->where('taller_id', $taller->id)
            ->pluck('slug')
            ->all();

        $orden = 1;

        foreach (CategoriaRepuesto::BASE as $slug => $nombre) {
            if (! in_array($slug, $existentes, true)) {
                CategoriaRepuesto::create([
                    'taller_id' => $taller->id,
                    'nombre' => $nombre,
                    'slug' => $slug,
                    'orden' => $orden,
                ]);
            }

            $orden++;
        }
    }
}
