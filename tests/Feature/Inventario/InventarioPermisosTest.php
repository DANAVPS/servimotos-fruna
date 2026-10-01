<?php

namespace Tests\Feature\Inventario;

use App\Models\CategoriaRepuesto;
use App\Models\Repuesto;
use App\Models\Taller;
use App\Models\User;
use App\Services\InventarioService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsaTaller;
use Tests\TestCase;

class InventarioPermisosTest extends TestCase
{
    use RefreshDatabase, UsaTaller;

    private Taller $taller;

    private Repuesto $repuesto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->taller = Taller::factory()->create();
        $this->repuesto = Repuesto::factory()->create([
            'taller_id' => $this->taller->id,
            'precio_venta' => 20000,
            'precio_costo' => 12000,
        ]);
    }

    private function datosValidos(array $extra = []): array
    {
        return [
            'nombre' => 'Filtro de aceite K&N',
            'precio_costo' => 6600,
            'precio_venta' => 11000,
            'stock_actual' => 12,
            'stock_minimo' => 3,
            ...$extra,
        ];
    }

    public function test_el_mecanico_no_puede_crear_editar_ni_eliminar_repuestos(): void
    {
        $mecanico = User::factory()->mecanico()->deTaller($this->taller)->create();

        $this->actingAs($mecanico)->get(route('inventario.create'))->assertForbidden();
        $this->actingAs($mecanico)->post(route('inventario.store'), $this->datosValidos())->assertForbidden();
        $this->actingAs($mecanico)->get(route('inventario.edit', $this->repuesto))->assertForbidden();
        $this->actingAs($mecanico)
            ->put(route('inventario.update', $this->repuesto), $this->datosValidos(['precio_venta' => 1]))
            ->assertForbidden();
        $this->actingAs($mecanico)
            ->delete(route('inventario.destroy', $this->repuesto), ['confirmo' => '1', 'confirmacion' => 'ELIMINAR'])
            ->assertForbidden();

        $this->assertEquals(20000, $this->repuesto->fresh()->precio_venta);
        $this->assertNotSoftDeleted($this->repuesto);
    }

    public function test_el_mecanico_si_puede_ver_el_inventario(): void
    {
        $mecanico = User::factory()->mecanico()->deTaller($this->taller)->create();

        $this->actingAs($mecanico)->get(route('inventario.index'))->assertOk();
    }

    public function test_el_modelo_bloquea_cambios_de_precio_de_un_mecanico_aunque_un_endpoint_olvide_la_policy(): void
    {
        $mecanico = User::factory()->mecanico()->deTaller($this->taller)->create();
        $this->actingAs($mecanico);
        $this->enTaller($this->taller);

        try {
            $this->repuesto->update(['precio_venta' => 1]);
            $this->fail('Se esperaba AuthorizationException.');
        } catch (AuthorizationException) {
            // esperado
        }

        $this->assertEquals(20000, $this->repuesto->fresh()->precio_venta);

        $this->expectException(AuthorizationException::class);
        Repuesto::create(['nombre' => 'Inventado', 'precio_costo' => 1, 'precio_venta' => 1]);
    }

    public function test_el_mecanico_si_descuenta_stock_al_liquidar(): void
    {
        $mecanico = User::factory()->mecanico()->deTaller($this->taller)->create();
        $this->actingAs($mecanico);
        $this->enTaller($this->taller);

        app(InventarioService::class)->descontarStock($this->repuesto->id, 2);

        $this->assertSame(8, $this->repuesto->fresh()->stock_actual);
    }

    public function test_la_administradora_crea_un_repuesto_con_categoria_y_compatibilidades(): void
    {
        $admin = User::factory()->administradora()->deTaller($this->taller)->create();
        $this->enTaller($this->taller);
        $categoria = CategoriaRepuesto::where('slug', 'filtros')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('inventario.store'), $this->datosValidos([
                'categoria_repuesto_id' => $categoria->id,
                'codigo_sku' => ' rep-fil-001 ',
                'compatibilidades' => 'Pulsar NS 200 / Duke 200',
            ]))
            ->assertRedirect(route('inventario.index'));

        $repuesto = Repuesto::where('codigo_sku', 'REP-FIL-001')->firstOrFail();
        $this->assertSame($this->taller->id, $repuesto->taller_id);
        $this->assertSame($categoria->id, $repuesto->categoria_repuesto_id);
        $this->assertCount(2, $repuesto->compatibilidades);
    }

    public function test_el_formulario_anterior_con_modelo_compatible_sigue_funcionando(): void
    {
        $admin = User::factory()->administradora()->deTaller($this->taller)->create();

        $this->actingAs($admin)
            ->put(route('inventario.update', $this->repuesto), $this->datosValidos([
                'modelo_compatible' => 'FZ 150 / XTZ 125',
            ]))
            ->assertRedirect(route('inventario.index'));

        $this->assertSame(
            ['FZ 150', 'XTZ 125'],
            $this->repuesto->fresh()->compatibilidades->pluck('moto_referencia')->all()
        );
    }

    public function test_no_se_puede_usar_una_categoria_ni_un_sku_ajeno_o_repetido(): void
    {
        $admin = User::factory()->administradora()->deTaller($this->taller)->create();
        $otroTaller = Taller::factory()->create();
        $categoriaAjena = CategoriaRepuesto::withoutGlobalScopes()->where('taller_id', $otroTaller->id)->first();

        $this->repuesto->forceFill(['codigo_sku' => 'REP-001'])->save();

        $this->actingAs($admin)
            ->post(route('inventario.store'), $this->datosValidos([
                'categoria_repuesto_id' => $categoriaAjena->id,
                'codigo_sku' => 'rep-001',
            ]))
            ->assertSessionHasErrors(['categoria_repuesto_id', 'codigo_sku']);
    }

    public function test_la_administradora_de_otro_taller_no_puede_editar_el_repuesto(): void
    {
        $adminAjena = User::factory()->administradora()->create();

        $this->actingAs($adminAjena)
            ->put(route('inventario.update', $this->repuesto), $this->datosValidos(['precio_venta' => 1]))
            ->assertNotFound();

        $this->assertEquals(20000, $this->repuesto->fresh()->precio_venta);
    }
}
