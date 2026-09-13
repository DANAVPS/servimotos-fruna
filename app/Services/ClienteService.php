<?php

namespace App\Services;

use App\Models\Cliente;
use App\Support\TenantManager;

class ClienteService
{
    public function __construct(private TenantManager $tenant)
    {
    }

    public function registrar(array $datos): Cliente
    {
        return Cliente::create([
            ...$datos,
            'estado_cliente' => 'activo',
            'fecha_ultima_visita' => now(),
        ]);
    }

    public function registrarInteraccion(Cliente $cliente): void
    {
        $cliente->update([
            'fecha_ultima_visita' => now(),
            'estado_cliente' => 'activo',
        ]);
    }

    /**
     * Reinscribe a un cliente archivado como si fuera nuevo,
     * conforme a la regla de negocio 2.4 del prompt base.
     */
    public function reinscribir(Cliente $clienteArchivado, array $datosActualizados): Cliente
    {
        $clienteArchivado->update([
            ...$datosActualizados,
            'estado_cliente' => 'activo',
            'fecha_ultima_visita' => now(),
        ]);

        return $clienteArchivado->fresh();
    }
}
