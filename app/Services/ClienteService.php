<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Moto;
use App\Support\TenantManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ClienteService
{
    private const CAMPOS_CLIENTE = ['nombre', 'cedula_nit', 'telefono', 'correo', 'tipo_cliente_id'];

    private const CAMPOS_MOTO = ['placa', 'marca', 'modelo', 'anio'];

    public function __construct(private TenantManager $tenant)
    {
    }

    public function registrar(array $datos): Cliente
    {
        return Cliente::create([
            ...Arr::only($datos, self::CAMPOS_CLIENTE),
            'estado_cliente' => 'activo',
            'fecha_ultima_visita' => now(),
        ]);
    }

    /**
     * Cliente y su moto en una sola transacción: si la moto falla (placa
     * repetida, etc.) no queda un cliente huérfano.
     *
     * @return array{cliente: Cliente, moto: Moto}
     */
    public function registrarConMoto(array $datosCliente, array $datosMoto): array
    {
        return DB::transaction(function () use ($datosCliente, $datosMoto) {
            $cliente = $this->registrar($datosCliente);

            $moto = $cliente->motos()->create(Arr::only($datosMoto, self::CAMPOS_MOTO));

            return ['cliente' => $cliente, 'moto' => $moto];
        });
    }

    /**
     * Datos de contacto, tipo, estado manual y motos, en una transacción:
     *  - `motos[{id}]`: edición de motos existentes (el request ya verificó que son del cliente).
     *  - `nueva_moto`: alta opcional de otra moto.
     */
    public function actualizar(Cliente $cliente, array $datos): Cliente
    {
        return DB::transaction(function () use ($cliente, $datos) {
            $cliente->update(Arr::only($datos, self::CAMPOS_CLIENTE));

            foreach ($datos['motos'] ?? [] as $motoId => $datosMoto) {
                $cliente->motos()->findOrFail($motoId)->update(Arr::only($datosMoto, self::CAMPOS_MOTO));
            }

            if (! empty($datos['nueva_moto']['placa'])) {
                $cliente->motos()->create(Arr::only($datos['nueva_moto'], self::CAMPOS_MOTO));
            }

            if (isset($datos['estado_cliente']) && $datos['estado_cliente'] !== $cliente->estado_cliente) {
                $this->cambiarEstado($cliente, $datos['estado_cliente']);
            }

            return $cliente->fresh();
        });
    }

    /**
     * Cambio manual de estado por la Administradora. Pasar a "activo" cuenta
     * como interacción (renueva fecha_ultima_visita), porque si no el job
     * mensual de ciclo de vida (Fase 5) lo volvería a hibernar/archivar.
     */
    public function cambiarEstado(Cliente $cliente, string $estado): Cliente
    {
        $cambios = ['estado_cliente' => $estado];

        if ($estado === 'activo') {
            $cambios['fecha_ultima_visita'] = now();
        }

        $cliente->update($cambios);

        return $cliente->fresh();
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
            ...Arr::only($datosActualizados, self::CAMPOS_CLIENTE),
            'estado_cliente' => 'activo',
            'fecha_ultima_visita' => now(),
        ]);

        return $clienteArchivado->fresh();
    }
}
