<?php

namespace Tests\Feature\Inventario;

use App\Models\CategoriaRepuesto;
use App\Models\Repuesto;
use App\Models\RepuestoCompatibilidad;
use App\Models\Taller;
use App\Services\InventarioImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsaTaller;
use Tests\TestCase;

class InventarioImportTest extends TestCase
{
    use RefreshDatabase, UsaTaller;

    private function importar(Taller $taller): array
    {
        return app(InventarioImportService::class)
            ->importar(database_path('seeders/data/inventario.csv'), $taller);
    }

    public function test_importa_el_excel_real_con_categorias_y_compatibilidades(): void
    {
        $taller = Taller::factory()->create();

        $resultado = $this->importar($taller);

        $this->assertSame(265, $resultado['creados']);

        $this->enTaller($taller);
        $this->assertSame(265, Repuesto::count());
        $this->assertSame(0, Repuesto::whereNull('categoria_repuesto_id')->count());

        $filtro = Repuesto::where('codigo_sku', 'REP-FIL-001')->firstOrFail();
        $this->assertSame('filtros', $filtro->categoria->slug);
        $this->assertSame(
            ['Duke 200', 'Pulsar NS 200'],
            $filtro->compatibilidades->pluck('moto_referencia')->all()
        );
        $this->assertSame('Pulsar NS 200 / Duke 200', $filtro->modelo_compatible);
        $this->assertTrue(RepuestoCompatibilidad::where('taller_id', $taller->id)->exists());
    }

    public function test_el_seeder_aplica_las_correcciones_acordadas(): void
    {
        $taller = Taller::factory()->create();

        $resultado = app(InventarioImportService::class)->importar(
            database_path('seeders/data/inventario.csv'),
            $taller,
            correcciones: require database_path('seeders/data/inventario_correcciones.php'),
        );

        $this->assertSame(264, $resultado['creados']);

        $this->enTaller($taller);
        $this->assertSame(2, Repuesto::where('codigo_sku', 'REP-ARR-024-2')->value('stock_actual'));
        $this->assertFalse(Repuesto::where('codigo_sku', 'REP-ARR-025-2')->exists());
        $this->assertEquals(42000, Repuesto::where('codigo_sku', 'INS-LUB-004')->value('precio_venta'));
        $this->assertSame('CR8E', Repuesto::buscar('cr8e')->value('referencia_proveedor'));
        $this->assertTrue(Repuesto::where('nombre', 'Empaque tapa bolante Varroc')->exists());
    }

    public function test_buscar_duke_200_trae_el_filtro_de_aceite_y_el_kit(): void
    {
        $taller = Taller::factory()->create();
        $this->importar($taller);
        $this->enTaller($taller);

        $skus = Repuesto::buscar('Duke 200')->pluck('codigo_sku')->filter()->sort()->values()->all();

        $this->assertContains('REP-FIL-001', $skus);
        $this->assertContains('REP-ARR-014', $skus);
    }

    public function test_reimportar_no_duplica_ni_pisa_correcciones_ni_revive_eliminados(): void
    {
        $taller = Taller::factory()->create();
        $this->importar($taller);
        $this->enTaller($taller);

        $corregido = Repuesto::where('codigo_sku', 'REP-FIL-001')->first();
        $corregido->update(['nombre' => 'Filtro aceite K&N KN-204', 'precio_venta' => 12500]);

        $sinSku = Repuesto::whereNull('codigo_sku')->where('nombre', 'Empaque culatín AKT')->first();
        $sinSku->delete();

        $resultado = $this->importar($taller);

        $this->assertSame(0, $resultado['creados']);
        $this->assertSame(265, $resultado['existentes']);
        $this->assertSame('Filtro aceite K&N KN-204', $corregido->fresh()->nombre);
        $this->assertEquals(12500, $corregido->fresh()->precio_venta);
        $this->assertSame(264, Repuesto::count());
    }

    public function test_cada_taller_importa_su_propio_inventario(): void
    {
        $tallerA = Taller::factory()->create();
        $tallerB = Taller::factory()->create();

        $this->importar($tallerA);
        $this->importar($tallerB);

        $this->enTaller($tallerA);
        $this->assertSame(265, Repuesto::count());
        $categoriasDeA = CategoriaRepuesto::pluck('id');
        $this->assertTrue(Repuesto::pluck('categoria_repuesto_id')->every(fn ($id) => $categoriasDeA->contains($id)));

        $this->enTaller($tallerB);
        $this->assertSame(265, Repuesto::count());
    }
}
