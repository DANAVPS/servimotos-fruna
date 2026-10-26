<?php

namespace App\Policies;

use App\Models\OrdenServicio;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrdenServicioPolicy
{
    /**
     * Regla crítica: SOLO Administradora (o SuperAdmin) puede marcar
     * una orden como pagada. El Mecánico jamás debe pasar esta verificación,
     * sin importar desde qué dispositivo o vista intente hacerlo.
     */
    public function marcarPagada(User $user, OrdenServicio $orden): bool
    {
        if (! $this->perteneceAlMismoTaller($user, $orden)) {
            return false;
        }

        return $user->esSuperAdmin() || $user->esAdministradora();
    }

    /**
     * Corregir la factura: solo administrativos (además piden PIN en el
     * controlador). Una orden pagada nunca se corrige.
     */
    public function corregirFactura(User $user, OrdenServicio $orden): Response
    {
        if (! $this->perteneceAlMismoTaller($user, $orden) || ! $user->esAdministrativo()) {
            return Response::deny('Solo la administradora puede corregir una factura.');
        }

        if ($orden->estado === 'pagada') {
            return Response::deny('Orden ya pagada. Para corregir, cree una nueva orden.');
        }

        return Response::allow();
    }

    /**
     * Orden manual: cualquiera del taller, incluido el mecánico. El SuperAdmin
     * sin taller no puede (la orden necesita un taller; falta su selector).
     */
    public function create(User $user): bool
    {
        return $user->taller_id !== null
            && ($user->esAdministrativo() || $user->esMecanico());
    }

    /** PDF de la factura (precios, totales, contacto del cliente): solo administrativos. */
    public function verFactura(User $user, OrdenServicio $orden): bool
    {
        return $this->perteneceAlMismoTaller($user, $orden) && $user->esAdministrativo();
    }

    public function verPanel(User $user, OrdenServicio $orden): bool
    {
        return $this->perteneceAlMismoTaller($user, $orden);
    }

    public function cambiarEstado(User $user, OrdenServicio $orden): bool
    {
        if (! $this->perteneceAlMismoTaller($user, $orden)) {
            return false;
        }

        // El mecánico solo puede mover estados operativos, nunca a "pagada".
        return $user->esSuperAdmin() || $user->esAdministradora() || $user->esMecanico();
    }

    /**
     * Emitir la factura (fijar mano de obra, totales y número): solo
     * administrativos. El mecánico registra el trabajo (liquidar), no el precio.
     */
    public function emitirFactura(User $user, OrdenServicio $orden): bool
    {
        return $this->perteneceAlMismoTaller($user, $orden) && $user->esAdministrativo();
    }

    /** Registrar el trabajo (repuestos, cantidades, observaciones): quien trabaja la orden, incluido el mecánico. */
    public function liquidar(User $user, OrdenServicio $orden): bool
    {
        return $this->cambiarEstado($user, $orden);
    }

    /**
     * Asignar o cambiar el mecánico de la orden. El mecánico también puede:
     * al pasar una orden a revisión elige quién la toma.
     */
    public function asignarMecanico(User $user, OrdenServicio $orden): bool
    {
        return $this->cambiarEstado($user, $orden);
    }

    private function perteneceAlMismoTaller(User $user, OrdenServicio $orden): bool
    {
        return $user->esSuperAdmin() || $user->taller_id === $orden->taller_id;
    }
}
