<?php

namespace Tests\Feature\Inventario;

use App\Models\Repuesto;
use App\Models\Taller;
use App\Services\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsaTaller;
use Tests\TestCase;

class RepuestoBusquedaTest extends TestCase
{
    use RefreshDatabase, UsaTaller;

    private InventarioService $inventario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventario = app(InventarioService::class);
        $this->enTaller(Taller::factory()->create());
    }

    private function crear(array $datos): Repuesto
    {
        return $this->inventario->crear([
            'precio_costo' => 6000,
            'precio_venta' => 10000,
            'stock_actual' => 5,
            'stock_minimo' => 2,
            ...$datos,
        ]);
    }

    private function nombres(string $consulta): array
    {
        return Repuesto::buscar($consulta)->orderBy('nombre')->pluck('nombre')->all();
    }

    public function test_busca_por_moto_compatible_sin_importar_espacios_ni_mayusculas(): void
    {
        $this->crear(['nombre' => 'Filtro de aire', 'compatibilidades' => 'FZ 16 / SZR']);
        $this->crear(['nombre' => 'Filtro de aceite', 'compatibilidades' => 'Pulsar NS 200']);

        $this->assertSame(['Filtro de aire'], $this->nombres('fz16'));
        $this->assertSame(['Filtro de aire'], $this->nombres('FZ-16'));
        $this->assertSame(['Filtro de aceite'], $this->nombres('ns200'));
    }

    public function test_busca_sin_tildes_y_con_varias_palabras(): void
    {
        $this->crear(['nombre' => 'Bujías pata larga', 'compatibilidades' => 'Boxer']);
        $this->crear(['nombre' => 'Filtro de aceite K&N', 'compatibilidades' => 'Duke 200']);
        $this->crear(['nombre' => 'Filtro de aire', 'compatibilidades' => 'Boxer']);

        $this->assertSame(['Bujías pata larga'], $this->nombres('bujia'));
        $this->assertSame(['Filtro de aceite K&N'], $this->nombres('filtro duke 200'));
        $this->assertSame(['Filtro de aire'], $this->nombres('filtro boxer'));
    }

    public function test_busca_por_sku_referencia_y_marca(): void
    {
        $this->crear(['nombre' => 'Filtro A', 'codigo_sku' => 'REP-FIL-013', 'referencia_proveedor' => '36JR0043']);
        $this->crear(['nombre' => 'Filtro B', 'marca' => 'Kontrol']);

        $this->assertSame(['Filtro A'], $this->nombres('rep-fil-013'));
        $this->assertSame(['Filtro A'], $this->nombres('36JR0043'));
        $this->assertSame(['Filtro B'], $this->nombres('kontrol'));
    }

    public function test_actualizar_compatibilidades_refresca_el_indice(): void
    {
        $repuesto = $this->crear(['nombre' => 'Kit de arrastre', 'compatibilidades' => 'Boxer CT 100']);

        $this->inventario->actualizar($repuesto, ['compatibilidades' => 'Duke 200']);

        $this->assertSame([], $this->nombres('boxer'));
        $this->assertSame(['Kit de arrastre'], $this->nombres('duke'));
        $this->assertSame('Duke 200', $repuesto->fresh()->modelo_compatible);
    }

    public function test_actualizar_sin_enviar_compatibilidades_las_conserva(): void
    {
        $repuesto = $this->crear(['nombre' => 'Kit de arrastre', 'compatibilidades' => 'Boxer CT 100']);

        $this->inventario->actualizar($repuesto, ['nombre' => 'Kit de arrastre PXR']);

        $this->assertSame(['Kit de arrastre PXR'], $this->nombres('boxer pxr'));
    }

    public function test_una_busqueda_vacia_no_filtra(): void
    {
        $this->crear(['nombre' => 'Uno']);
        $this->crear(['nombre' => 'Dos']);

        $this->assertCount(2, $this->nombres('   '));
    }
}
