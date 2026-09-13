<?php

namespace Database\Seeders;

use App\Models\Mecanico;
use App\Models\Moto;
use App\Models\OrdenServicio;
use App\Models\Taller;
use App\Support\TenantManager;
use Illuminate\Database\Seeder;

class OrdenServicioSeeder extends Seeder
{
    public function run(): void
    {
        $taller = Taller::first();

        if ($taller) {
            app(TenantManager::class)->establecer($taller->id);
        }

        $motos = Moto::with('cliente')->get();
        $mecanicos = Mecanico::disponible()->get();

        $estados = [
            'en_espera_revision',
            'en_revision',
            'reparacion',
            'espera_repuesto',
            'terminada',
            'listo_para_reclamar',
            'pagada',
        ];

        foreach ($motos as $index => $moto) {
            $estado = $estados[$index % count($estados)];
            $tieneMecanico = $estado !== 'en_espera_revision';

            OrdenServicio::create([
                'taller_id' => $taller?->id,
                'fecha_hora_ingreso' => now()->subDays(rand(0, 15)),
                'estado' => $estado,
                'descripcion_falla' => 'Revisión general y mantenimiento preventivo solicitado por el cliente.',
                'mecanico_id' => $tieneMecanico ? $mecanicos->random()->id : null,
                'cliente_id' => $moto->cliente_id,
                'moto_id' => $moto->id,
                'total' => in_array($estado, ['listo_para_reclamar', 'pagada']) ? 85000 : 0,
            ]);
        }
    }
}   
