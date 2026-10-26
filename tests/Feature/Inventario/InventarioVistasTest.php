<?php

namespace Tests\Feature\Inventario;

use App\Models\CategoriaRepuesto;
use App\Models\Distribuidor;
use App\Models\Repuesto;
use App\Models\Taller;
use App\Models\User;
use App\Services\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsaTaller;
use Tests\TestCase;

class InventarioVistasTest extends TestCase
{
    use RefreshDatabase, UsaTaller;

    private Taller $taller;

    private Repuesto $filtro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->taller = Taller::factory()->create();
        $this->enTaller($this->taller);

        $categoria = CategoriaRepuesto::where('slug', 'filtros')->first();
        $this->filtro = app(InventarioService::class)->crear([
            'nombre' => 'Filtro de aceite K&N',
            'categoria_repuesto_id' => $categoria->id,
            'codigo_sku' => 'REP-FIL-001',
            'precio_costo' => 6600,
            'precio_venta' => 11000,
            'stock_actual' => 1,
            'stock_minimo' => 3,
            'compatibilidades' => 'Pulsar NS 200 / Duke 200',
        ]);
        app(InventarioService::class)->crear([
            'nombre' => 'Kit de arrastre PXR',
            'precio_costo' => 88200,
            'precio_venta' => 147000,
            'stock_actual' => 5,
            'stock_minimo' => 1,
        ]);

        $distribuidor = Distribuidor::factory()->create(['taller_id' => $this->taller->id, 'nombre' => 'Motopartes Ejemplo']);
        $distribuidor->repuestos()->attach($this->filtro->id, ['taller_id' => $this->taller->id, 'tiempo_entrega_dias' => 2]);

        $this->enTaller(null);
    }

    public function test_la_administradora_ve_el_inventario_agrupado_con_costos_y_acciones(): void
    {
        $admin = User::factory()->administradora()->deTaller($this->taller)->create();

        $this->actingAs($admin)->get(route('inventario.index'))
            ->assertOk()
            ->assertSeeInOrder(['Filtros', 'Filtro de aceite K&N', 'Sin categoría', 'Kit de arrastre PXR'])
            ->assertSee('Pulsar NS 200 / Duke 200')
            ->assertSee('1 repuesto en stock bajo')
            ->assertSee('Motopartes Ejemplo')
            ->assertSee('Solicitar repuestos a este distribuidor')
            ->assertSee('$6.600')
            ->assertSee(route('inventario.edit', $this->filtro))
            ->assertSee('+ Agregar repuesto');
    }

    public function test_el_mecanico_ve_el_inventario_sin_costos_ni_botones_de_gestion(): void
    {
        $mecanico = User::factory()->mecanico()->deTaller($this->taller)->create();

        $this->actingAs($mecanico)->get(route('inventario.index'))
            ->assertOk()
            ->assertSee('Filtro de aceite K&N')
            ->assertSee('$11.000')
            ->assertDontSee('$6.600')
            ->assertDontSee('Costo')
            ->assertDontSee(route('inventario.edit', $this->filtro))
            ->assertDontSee('+ Agregar repuesto')
            ->assertDontSee('Solicitar repuestos a este distribuidor')
            ->assertDontSee(route('distribuidores.index'));
    }

    public function test_el_buscador_filtra_por_moto(): void
    {
        $admin = User::factory()->administradora()->deTaller($this->taller)->create();

        $this->actingAs($admin)->get(route('inventario.index', ['buscar' => 'duke 200']))
            ->assertOk()
            ->assertSee('Filtro de aceite K&N')
            ->assertDontSee('Kit de arrastre PXR')
            ->assertSee('1 repuesto encontrado');
    }

    public function test_formularios_de_crear_y_editar_cargan(): void
    {
        $admin = User::factory()->administradora()->deTaller($this->taller)->create();

        $this->actingAs($admin)->get(route('inventario.create'))->assertOk()->assertSee('Motos compatibles');
        $this->actingAs($admin)->get(route('inventario.edit', $this->filtro))
            ->assertOk()
            ->assertSee('Duke 200')
            ->assertSee('Escribe', false)
            ->assertSee('ELIMINAR');
    }

    public function test_quitar_todos_los_distribuidores_desde_el_formulario_los_desvincula(): void
    {
        $admin = User::factory()->administradora()->deTaller($this->taller)->create();

        $this->actingAs($admin)
            ->put(route('inventario.update', $this->filtro), [
                'nombre' => $this->filtro->nombre,
                'precio_costo' => 6600,
                'precio_venta' => 11000,
                'stock_actual' => 1,
                'stock_minimo' => 3,
                'distribuidores_enviados' => '1',
            ])
            ->assertRedirect(route('inventario.index'));

        $this->assertSame(0, $this->filtro->distribuidores()->count());
    }

    public function test_el_buscador_json_no_expone_costos_al_mecanico(): void
    {
        $mecanico = User::factory()->mecanico()->deTaller($this->taller)->create();
        $admin = User::factory()->administradora()->deTaller($this->taller)->create();

        $respuestaMecanico = $this->actingAs($mecanico)->getJson(route('inventario.buscar', ['q' => 'duke']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Filtro de aceite K&N')
            ->assertJsonPath('data.0.precio_venta', 11000)
            ->assertJsonPath('data.0.compatibilidad', 'Pulsar NS 200 / Duke 200');

        $this->assertArrayNotHasKey('precio_costo', $respuestaMecanico->json('data.0'));
        $this->assertStringNotContainsString('6600', $respuestaMecanico->getContent());

        $this->actingAs($admin)->getJson(route('inventario.buscar', ['q' => 'duke']))
            ->assertJsonPath('data.0.precio_costo', 6600);
    }

    public function test_el_buscador_json_ignora_consultas_cortas_y_otros_talleres(): void
    {
        $adminAjena = User::factory()->administradora()->create();

        $this->actingAs($adminAjena)->getJson(route('inventario.buscar', ['q' => 'duke']))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $admin = User::factory()->administradora()->deTaller($this->taller)->create();
        $this->actingAs($admin)->getJson(route('inventario.buscar', ['q' => 'd']))
            ->assertJsonCount(0, 'data');
    }
}
