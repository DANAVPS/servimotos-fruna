<?php

namespace App\Services;

use App\Models\ConfiguracionTaller;
use App\Models\OrdenServicio;
use App\Support\TenantManager;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrdenService
{
    public function __construct(private TenantManager $tenant)
    {
    }

    public function crear(array $datos): OrdenServicio
    {
        return OrdenServicio::create([
            ...$datos,
            'estado' => 'en_espera_revision',
            'fecha_hora_ingreso' => now(),
        ]);
    }

    /**
     * Cambia el estado de la orden, validando la capacidad máxima
     * simultánea del taller antes de permitir el paso a estados activos.
     */
    public function cambiarEstado(OrdenServicio $orden, string $nuevoEstado): OrdenServicio
    {
        $estadosQueOcupanCapacidad = ['en_revision', 'reparacion', 'espera_repuesto'];

        if (in_array($nuevoEstado, $estadosQueOcupanCapacidad, true)) {
            $this->validarCapacidadDisponible($orden);
        }

        return DB::transaction(function () use ($orden, $nuevoEstado) {
            $orden->update(['estado' => $nuevoEstado]);

            return $orden->fresh();
        });
    }

    /**
     * Regla crítica de negocio: SOLO se llama tras pasar la Policy en el
     * controlador. Este método asume que la autorización ya ocurrió.
     */
    public function marcarPagada(OrdenServicio $orden): OrdenServicio
    {
        if ($orden->estado !== 'listo_para_reclamar') {
            throw new RuntimeException(
                'Solo se puede marcar como pagada una orden en estado "listo_para_reclamar".'
            );
        }

        $orden->update(['estado' => 'pagada']);

        return $orden->fresh();
    }

    private function validarCapacidadDisponible(OrdenServicio $orden): void
    {
        $config = ConfiguracionTaller::where('taller_id', $this->tenant->tallerId())->first();
        $capacidadMaxima = $config?->capacidad_maxima_simultanea ?? 5;

        $ocupadas = OrdenServicio::activasEnTaller()
            ->where('id', '!=', $orden->id)
            ->count();

        if ($ocupadas >= $capacidadMaxima) {
            throw new RuntimeException(
                "El taller ha alcanzado su capacidad máxima simultánea ({$capacidadMaxima} motos)."
            );
        }
    }
}
