<?php

namespace Database\Seeders;

use App\Models\CategoriaRepuesto;
use App\Models\Distribuidor;
use App\Models\Repuesto;
use App\Models\Taller;
use App\Support\TenantManager;
use Illuminate\Database\Seeder;

/**
 * Distribuidores de EJEMPLO (datos ficticios) para probar alertas y pedidos.
 * Algunas categorías quedan sin distribuidor a propósito, para ver el grupo
 * "Sin distribuidor asignado".
 */
class DistribuidorSeeder extends Seeder
{
    private const DISTRIBUIDORES = [
        [
            'datos' => [
                'nombre' => 'Distribuidora Ejemplo Filtros y Lubricantes',
                'contacto' => 'Contacto de prueba',
                'telefono_whatsapp' => '3000000001',
                'email' => 'pedidos-filtros@example.com',
                'notas' => 'Distribuidor de ejemplo. Entrega en 2 días hábiles.',
            ],
            'categorias' => ['filtros', 'lubricante', 'bujias'],
            'tiempo_entrega_dias' => 2,
        ],
        [
            'datos' => [
                'nombre' => 'Distribuidora Ejemplo Transmisión',
                'contacto' => 'Contacto de prueba',
                'telefono_whatsapp' => '3000000002',
                'email' => 'pedidos-transmision@example.com',
                'notas' => 'Distribuidor de ejemplo. Kits de arrastre, cadenas, frenos y baterías.',
            ],
            'categorias' => ['kit-de-arrastre', 'cadena', 'frenos', 'bateria'],
            'tiempo_entrega_dias' => 3,
        ],
        [
            'datos' => [
                'nombre' => 'Distribuidora Ejemplo Motor y Eléctricos',
                'contacto' => 'Contacto de prueba',
                'telefono_whatsapp' => '3000000003',
                'email' => null,
                'notas' => 'Distribuidor de ejemplo. Empaques, eléctricos y partes de motor.',
            ],
            'categorias' => [
                'culatin', 'culata', 'clutch', 'empaques-varios', 'bobinas', 'relay',
                'regulador', 'tensor', 'cadenillas', 'kit-cilindro', 'cilindro-piston',
            ],
            'tiempo_entrega_dias' => 5,
        ],
    ];

    public function run(): void
    {
        $taller = Taller::orderBy('id')->first();

        if (! $taller) {
            return;
        }

        app(TenantManager::class)->establecer($taller->id);

        foreach (self::DISTRIBUIDORES as $config) {
            $distribuidor = Distribuidor::firstOrCreate(
                ['nombre' => $config['datos']['nombre']],
                $config['datos']
            );

            $categoriaIds = CategoriaRepuesto::whereIn('slug', $config['categorias'])->pluck('id');

            $vinculos = Repuesto::whereIn('categoria_repuesto_id', $categoriaIds)
                ->get()
                ->mapWithKeys(fn (Repuesto $repuesto) => [$repuesto->id => [
                    'taller_id' => $taller->id,
                    'codigo_proveedor' => $repuesto->referencia_proveedor,
                    'precio_costo_proveedor' => $repuesto->precio_costo,
                    'tiempo_entrega_dias' => $config['tiempo_entrega_dias'],
                ]])
                ->all();

            $distribuidor->repuestos()->syncWithoutDetaching($vinculos);
        }
    }
}
