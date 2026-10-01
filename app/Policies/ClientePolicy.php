<?php

namespace App\Policies;

use App\Models\Cliente;
use App\Models\User;

/**
 * Mecánico: solo lectura (los necesita al crear órdenes).
 * Administradora/SuperAdmin: gestión completa, incluido el estado y el tipo de cliente.
 */
class ClientePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->esSuperAdmin() || $user->taller_id !== null;
    }

    public function view(User $user, Cliente $cliente): bool
    {
        return $this->perteneceAlMismoTaller($user, $cliente);
    }

    public function create(User $user): bool
    {
        return $user->esAdministrativo();
    }

    /**
     * Alta rápida desde "Crear orden manual" (Bloque 3): cualquier usuario del
     * taller, incluido el mecánico, pero el cliente queda SIEMPRE como Regular.
     * El endpoint que use esta habilidad debe ignorar tipo_cliente_id.
     */
    public function crearDesdeOrden(User $user): bool
    {
        return $user->esSuperAdmin() || $user->taller_id !== null;
    }

    /**
     * Asignar o cambiar el tipo de cliente (afecta precios): solo administrativos.
     */
    public function asignarTipo(User $user): bool
    {
        return $user->esAdministrativo();
    }

    public function update(User $user, Cliente $cliente): bool
    {
        return $user->esAdministrativo() && $this->perteneceAlMismoTaller($user, $cliente);
    }

    private function perteneceAlMismoTaller(User $user, Cliente $cliente): bool
    {
        return $user->esSuperAdmin() || $user->taller_id === $cliente->taller_id;
    }
}
