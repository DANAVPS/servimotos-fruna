<?php

namespace App\Http\Controllers;

use App\Models\OrdenEstadoHistorial;
use App\Models\OrdenServicio;
use App\Scopes\ClienteActivoScope;
use App\Support\TarjetaOrden;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * "Mi día": tablero de solo lectura del mecánico con SUS órdenes activas.
 *
 * Sin dinero y sin datos de contacto del cliente: a la vista llegan arreglos
 * armados campo por campo (nunca modelos), así nada sensible puede colarse.
 */
class MiDiaController extends Controller
{
    /** Estados en los que la moto está en manos del mecánico. */
    private const ESTADOS_ACTIVOS = ['en_espera_revision', 'en_revision', 'reparacion', 'espera_repuesto', 'terminada'];

    private const ZONA_HORARIA = 'America/Bogota';

    public function __invoke(Request $request): View
    {
        $usuario = $request->user();

        // role:mecanico deja pasar al SuperAdmin; esta vista es solo del mecánico.
        abort_unless($usuario->esMecanico(), 403);

        $mecanico = $usuario->mecanico;
        $nombre = Str::of($mecanico?->nombre ?? $usuario->name)->before(' ');

        if ($mecanico === null) {
            return view('mi-dia', ['nombre' => $nombre, 'vinculado' => false]);
        }

        $config = TarjetaOrden::configuracionDe($mecanico->taller_id);

        $ordenes = OrdenServicio::query()
            ->where('mecanico_id', $mecanico->id)
            ->whereIn('estado', self::ESTADOS_ACTIVOS)
            ->with([
                'moto:id,placa,marca,modelo',
                'cliente' => fn ($q) => $q->withoutGlobalScope(ClienteActivoScope::class)->select('id', 'nombre'),
            ])
            ->orderBy('fecha_hora_ingreso') // la más antigua (más urgente) primero
            ->get()
            ->map(fn (OrdenServicio $orden) => [
                'id' => $orden->id,
                'estado' => $orden->estado,
                'placa' => $orden->moto->placa,
                'moto' => trim("{$orden->moto->marca} {$orden->moto->modelo}"),
                // Solo el primer nombre: sin apellidos ni datos de contacto.
                'cliente' => TarjetaOrden::primerNombre($orden->cliente?->nombre),
                'falla' => $orden->descripcion_falla ? Str::limit($orden->descripcion_falla, 90) : null,
                'dias' => $orden->diasEnTaller(),
                'semaforo' => $orden->semaforo($config),
                'trabajo_registrado' => TarjetaOrden::trabajoRegistrado($orden),
                'ingreso' => $orden->fecha_hora_ingreso->timezone(self::ZONA_HORARIA)->format('d/m/Y h:i a'),
            ]);

        // Lunes 00:00 en Colombia, pasado a la zona de la app (UTC) para comparar con la base.
        $inicioSemana = now(self::ZONA_HORARIA)->startOfWeek(CarbonInterface::MONDAY)->setTimezone(config('app.timezone'));

        return view('mi-dia', [
            'nombre' => $nombre,
            'vinculado' => true,
            'estados' => self::ESTADOS_ACTIVOS,
            'etiquetas' => TarjetaOrden::ETIQUETAS_ESTADO,
            'porEstado' => collect(self::ESTADOS_ACTIVOS)->mapWithKeys(fn ($estado) => [
                $estado => $ordenes->where('estado', $estado)->values(),
            ]),
            'enRojo' => $ordenes->where('semaforo', 'rojo')->sortByDesc('dias')->values(),
            'totalActivas' => $ordenes->count(),
            'terminadasSemana' => $this->terminadasDesde($mecanico->id, $inicioSemana),
            'umbralRojo' => $config->dias_alerta_roja,
        ]);
    }

    /** Órdenes de este mecánico que pasaron a "terminada" desde el lunes. */
    private function terminadasDesde(int $mecanicoId, DateTimeInterface $desde): int
    {
        return OrdenEstadoHistorial::query()
            ->where('estado_nuevo', 'terminada')
            ->where('evento', 'cambio_estado')
            ->where('created_at', '>=', $desde)
            ->whereHas('ordenServicio', fn ($q) => $q->where('mecanico_id', $mecanicoId))
            ->distinct()
            ->count('orden_servicio_id');
    }
}
