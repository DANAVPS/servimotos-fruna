<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\ConfiguracionTaller;
use App\Models\Mecanico;
use App\Models\Moto;
use App\Models\OrdenServicio;
use App\Models\TipoCliente;
use App\Models\User;
use App\Scopes\ClienteActivoScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrdenService
{
    private const ESTADOS_QUE_OCUPAN_CAPACIDAD = ['en_revision', 'reparacion', 'espera_repuesto'];

    /**
     * Estados a los que NO se llega moviendo la tarjeta:
     *  - listo_para_reclamar: solo liquidando (FacturaService::liquidar).
     *  - pagada: solo con marcarPagada().
     */
    private const DESTINOS_RESERVADOS = ['listo_para_reclamar', 'pagada'];

    /**
     * Estados de los que no se sale moviendo la tarjeta:
     *  - listo_para_reclamar: tiene stock descontado; se sale corrigiendo la factura o pagando.
     *  - pagada: es final.
     */
    private const ORIGENES_BLOQUEADOS = ['listo_para_reclamar', 'pagada'];

    public function __construct(private ClienteService $clienteService)
    {
    }

    /** Regla 2: toda orden nace en en_espera_revision, venga de donde venga. */
    public function crear(array $datos, ?User $user = null, ?string $observaciones = null): OrdenServicio
    {
        return DB::transaction(function () use ($datos, $user, $observaciones) {
            $orden = OrdenServicio::create([
                ...$datos,
                'estado' => 'en_espera_revision',
                'fecha_hora_ingreso' => now(),
            ]);

            $orden->registrarHistorial('creacion', null, $user, $observaciones);

            return $orden;
        });
    }

    /**
     * Orden manual desde el Kanban (ver StoreOrdenServicioRequest). Todo en
     * una transacción: si la orden falla, no quedan cliente ni moto sueltos.
     *
     * - Cliente nuevo: SIEMPRE Regular, lo cree quien lo cree.
     * - Cliente existente: la orden es una visita → fecha_ultima_visita = hoy
     *   y, si estaba hibernando, vuelve a activo.
     * - Moto nueva de un cliente existente: se registra sin PIN (es recepción,
     *   no edición) y queda anotado en el historial de la orden.
     */
    public function crearManual(array $datos, User $user): OrdenServicio
    {
        return DB::transaction(function () use ($datos, $user) {
            $notas = [];

            if (isset($datos['cliente_id'])) {
                $cliente = Cliente::withoutGlobalScope(ClienteActivoScope::class)->findOrFail($datos['cliente_id']);
                $this->registrarVisita($cliente);

                if (isset($datos['moto_id'])) {
                    $moto = Moto::where('cliente_id', $cliente->id)->findOrFail($datos['moto_id']);
                } else {
                    $moto = $cliente->motos()->create($datos['moto']);
                    $notas[] = "Moto nueva registrada en la recepción: {$moto->placa}.";
                }
            } else {
                $regular = TipoCliente::regularDelTaller($user->taller_id);

                ['cliente' => $cliente, 'moto' => $moto] = $this->clienteService->registrarConMoto(
                    [...$datos['cliente'], 'tipo_cliente_id' => $regular->id],
                    $datos['moto'],
                );
                $notas[] = 'Cliente nuevo registrado en la recepción (tipo Regular).';
            }

            $orden = $this->crear([
                'cliente_id' => $cliente->id,
                'moto_id' => $moto->id,
                'descripcion_falla' => $datos['descripcion_falla'] ?? null,
            ], $user, $notas ? implode(' ', $notas) : null);

            return $orden->load(['cliente', 'moto', 'mecanico']);
        });
    }

    /**
     * Cambio de estado del Kanban. Deja una fila en orden_estado_historial.
     * Entrar a en_revision exige mecánico (el que se pasa o el que ya tenga).
     */
    public function cambiarEstado(
        OrdenServicio $orden,
        string $nuevoEstado,
        User $user,
        ?string $observaciones = null,
        ?int $mecanicoId = null,
    ): OrdenServicio {
        if (in_array($nuevoEstado, self::DESTINOS_RESERVADOS, true)) {
            throw new RuntimeException($nuevoEstado === 'pagada'
                ? 'Una orden solo se marca como pagada con el botón "Marcar como pagada".'
                : 'Para pasar a "Listo para reclamar" hay que liquidar la orden.');
        }

        return DB::transaction(function () use ($orden, $nuevoEstado, $user, $observaciones, $mecanicoId) {
            $orden = OrdenServicio::whereKey($orden->id)->lockForUpdate()->firstOrFail();
            $estadoAnterior = $orden->estado;

            if (in_array($estadoAnterior, self::ORIGENES_BLOQUEADOS, true)) {
                throw new RuntimeException($estadoAnterior === 'pagada'
                    ? 'La orden ya está pagada y no puede cambiar de estado.'
                    : 'La orden ya está liquidada. Para modificarla, corrige la factura.');
            }

            if ($estadoAnterior === $nuevoEstado) {
                throw new RuntimeException('La orden ya está en ese estado.');
            }

            if (in_array($nuevoEstado, self::ESTADOS_QUE_OCUPAN_CAPACIDAD, true)
                && ! in_array($estadoAnterior, self::ESTADOS_QUE_OCUPAN_CAPACIDAD, true)) {
                $this->validarCapacidadDisponible($orden);
            }

            if ($mecanicoId !== null) {
                $orden->mecanico_id = $this->mecanicoActivo($mecanicoId, $orden->taller_id)->id;
            }

            if ($nuevoEstado === 'en_revision' && $orden->mecanico_id === null) {
                throw new RuntimeException('Asigna un mecánico para pasar la orden a revisión.');
            }

            $orden->estado = $nuevoEstado;
            $orden->save();

            // Al entrar a revisión queda constancia de quién la tomó.
            $orden->registrarHistorial(
                'cambio_estado',
                $estadoAnterior,
                $user,
                $observaciones,
                $nuevoEstado === 'en_revision' ? $orden->mecanico_id : $mecanicoId,
            );

            return $orden->fresh(['mecanico']);
        });
    }

    /**
     * Reasigna el mecánico sin cambiar el estado. No va al historial (sus
     * eventos son de estado); el mecánico vigente queda en la orden.
     */
    public function asignarMecanico(OrdenServicio $orden, int $mecanicoId): OrdenServicio
    {
        if ($orden->estado === 'pagada') {
            throw new RuntimeException('La orden ya está pagada; su mecánico no se puede cambiar.');
        }

        $orden->update(['mecanico_id' => $this->mecanicoActivo($mecanicoId, $orden->taller_id)->id]);

        return $orden->fresh(['mecanico']);
    }

    /**
     * Regla 1: SOLO Administradora o SuperAdmin. La Policy y la ruta ya lo
     * verificaron; se repite aquí como última defensa.
     * Regla 3: el método de pago es interno y no va a las observaciones.
     */
    public function marcarPagada(OrdenServicio $orden, string $metodoPago, User $user): OrdenServicio
    {
        if (! $user->esAdministrativo()) {
            throw new AuthorizationException('Solo la administradora puede marcar una orden como pagada.');
        }

        if (! array_key_exists($metodoPago, OrdenServicio::METODOS_PAGO)) {
            throw new RuntimeException('Método de pago no válido.');
        }

        return DB::transaction(function () use ($orden, $metodoPago, $user) {
            $orden = OrdenServicio::whereKey($orden->id)->lockForUpdate()->firstOrFail();

            if ($orden->estado !== 'listo_para_reclamar') {
                throw new RuntimeException(
                    'Solo se puede marcar como pagada una orden en estado "Listo para reclamar".'
                );
            }

            $orden->fill([
                'estado' => 'pagada',
                'metodo_pago' => $metodoPago,
                'pagada_at' => now(),
            ])->save();

            $orden->registrarHistorial('pago', 'listo_para_reclamar', $user);

            return $orden->fresh();
        });
    }

    private function registrarVisita(Cliente $cliente): void
    {
        $cliente->forceFill([
            'fecha_ultima_visita' => now(),
            'estado_cliente' => $cliente->estado_cliente === 'hibernando' ? 'activo' : $cliente->estado_cliente,
        ])->save();
    }

    private function mecanicoActivo(int $mecanicoId, int $tallerId): Mecanico
    {
        $mecanico = Mecanico::withoutGlobalScopes()
            ->where('taller_id', $tallerId)
            ->activos()
            ->find($mecanicoId);

        if ($mecanico === null) {
            throw new RuntimeException('El mecánico no existe o está inactivo.');
        }

        return $mecanico;
    }

    private function validarCapacidadDisponible(OrdenServicio $orden): void
    {
        $config = ConfiguracionTaller::withoutGlobalScopes()->where('taller_id', $orden->taller_id)->first();
        $capacidadMaxima = $config?->capacidad_maxima_simultanea ?? 5;

        $ocupadas = OrdenServicio::withoutGlobalScopes()
            ->delTaller($orden->taller_id)
            ->activasEnTaller()
            ->where('id', '!=', $orden->id)
            ->count();

        if ($ocupadas >= $capacidadMaxima) {
            throw new RuntimeException(
                "El taller ha alcanzado su capacidad máxima simultánea ({$capacidadMaxima} motos)."
            );
        }
    }
}
