<?php

namespace App\Observers;

use App\Models\Taller;
use App\Services\CategoriaRepuestoService;
use App\Services\TipoClienteService;

class TallerObserver
{
    public function __construct(
        private CategoriaRepuestoService $categorias,
        private TipoClienteService $tiposCliente,
    ) {
    }

    /**
     * Todo taller nuevo arranca con las categorías base de inventario y los
     * tipos de cliente base (Regular 0%, VIP/Flota 10%).
     */
    public function created(Taller $taller): void
    {
        $this->categorias->crearBase($taller);
        $this->tiposCliente->crearBase($taller);
    }
}
