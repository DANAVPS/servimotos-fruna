<?php

namespace Tests\Feature\Inventario;

use App\Models\CategoriaRepuesto;
use App\Models\Distribuidor;
use App\Models\Repuesto;
use App\Models\RepuestoCompatibilidad;
use App\Models\Taller;
use App\Services\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsaTaller;
use Tests\TestCase;

class AislamientoTallerTest extends TestCase
{
    use RefreshDatabase, UsaTaller;

    public function test_un_taller_nuevo_arranca_con_las_27_categorias_base(): void
    {
        $taller = Taller::factory()->create();
        $this->enTaller($taller);

        $this->assertSame(27, CategoriaRepuesto::count());
        $this->assertSame(
            array_keys(CategoriaRepuesto::BASE),
            CategoriaRepuesto::ordenadas()->pluck('slug')->all()
        );
    }

    public function test_un_taller_no_ve_repuestos_compatibilidades_categorias_ni_distribuidores_de_otro(): void
    {
        $tallerA = Taller::factory()->create();
        $tallerB = Taller::factory()->create();

        $this->enTaller($tallerA);
        $repuestoA = app(InventarioService::class)->crear([
            'nombre' => 'Filtro de A',
            'precio_costo' => 1,
            'precio_venta' => 2,
            'stock_actual' => 0,
            'stock_minimo' => 1,
            'compatibilidades' => 'Duke 200',
        ]);
        Distribuidor::factory()->create(['taller_id' => $tallerA->id]);
        CategoriaRepuesto::create(['nombre' => 'Solo de A']);

        $this->enTaller($tallerB);

        $this->assertSame(0, Repuesto::count());
        $this->assertSame(0, Repuesto::buscar('duke')->count());
        $this->assertSame(0, RepuestoCompatibilidad::count());
        $this->assertSame(0, Distribuidor::count());
        $this->assertSame(27, CategoriaRepuesto::count());
        $this->assertTrue(app(InventarioService::class)->alertasAgrupadasPorDistribuidor()->isEmpty());

        $this->assertSame($tallerA->id, $repuestoA->compatibilidades()->withoutGlobalScopes()->first()->taller_id);
    }
}
