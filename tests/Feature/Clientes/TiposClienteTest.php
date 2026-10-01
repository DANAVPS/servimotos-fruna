<?php

namespace Tests\Feature\Clientes;

use App\Models\Repuesto;
use App\Models\Taller;
use App\Models\TipoCliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsaTaller;
use Tests\TestCase;

class TiposClienteTest extends TestCase
{
    use RefreshDatabase, UsaTaller;

    private Taller $taller;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->taller = Taller::factory()->create();
        $this->admin = User::factory()->administradora()->deTaller($this->taller)->create();
    }

    public function test_un_taller_nuevo_arranca_con_regular_y_vip_flota(): void
    {
        $this->enTaller($this->taller);

        $this->assertSame(
            ['regular' => '0.00', 'vip-flota' => '10.00'],
            TipoCliente::orderBy('slug')->pluck('descuento_repuestos_pct', 'slug')->all()
        );
    }

    public function test_cada_taller_tiene_sus_propios_porcentajes(): void
    {
        $otro = Taller::factory()->create();
        TipoCliente::withoutGlobalScopes()->where('taller_id', $otro->id)->where('slug', 'vip-flota')
            ->update(['descuento_repuestos_pct' => 15]);

        $this->enTaller($this->taller);
        $this->assertEquals(10, TipoCliente::where('slug', 'vip-flota')->value('descuento_repuestos_pct'));

        $this->enTaller($otro);
        $this->assertEquals(15, TipoCliente::where('slug', 'vip-flota')->value('descuento_repuestos_pct'));
    }

    public function test_crear_y_editar_tipo_sin_cambiar_el_slug(): void
    {
        $this->actingAs($this->admin)
            ->post(route('tipos-cliente.store'), ['nombre' => 'Distribuidor', 'descuento_repuestos_pct' => '15,5', 'activo' => '1'])
            ->assertRedirect(route('tipos-cliente.index'))
            ->assertSessionHasNoErrors();

        $this->enTaller($this->taller);
        $tipo = TipoCliente::where('slug', 'distribuidor')->firstOrFail();
        $this->assertEquals(15.5, $tipo->descuento_repuestos_pct);

        $this->actingAs($this->admin)
            ->put(route('tipos-cliente.update', $tipo), ['nombre' => 'Distribuidor mayorista', 'descuento_repuestos_pct' => 18, 'activo' => '0'])
            ->assertSessionHasNoErrors();

        $tipo->refresh();
        $this->assertSame('distribuidor', $tipo->slug);
        $this->assertFalse($tipo->activo);
    }

    public function test_bloquea_descuentos_que_dejarian_precio_negativo_y_no_permite_desactivar_regular(): void
    {
        $this->enTaller($this->taller);
        $regular = TipoCliente::where('slug', 'regular')->first();

        $this->actingAs($this->admin)
            ->post(route('tipos-cliente.store'), ['nombre' => 'Regalado', 'descuento_repuestos_pct' => 101, 'activo' => '1'])
            ->assertSessionHasErrors(['descuento_repuestos_pct' => 'El descuento no puede ser mayor a 100%: dejaría precios negativos.']);

        $this->actingAs($this->admin)
            ->post(route('tipos-cliente.store'), ['nombre' => 'Negativo', 'descuento_repuestos_pct' => -5, 'activo' => '1'])
            ->assertSessionHasErrors('descuento_repuestos_pct');

        $this->actingAs($this->admin)
            ->put(route('tipos-cliente.update', $regular), ['nombre' => 'Regular', 'descuento_repuestos_pct' => 0, 'activo' => '0'])
            ->assertSessionHasErrors('activo');

        $this->assertTrue($regular->fresh()->activo);
    }

    public function test_la_base_de_datos_tambien_rechaza_descuentos_fuera_de_rango(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('tipos_cliente')->where('taller_id', $this->taller->id)->update(['descuento_repuestos_pct' => 120]);
    }

    public function test_advierte_cuando_el_descuento_deja_repuestos_bajo_el_costo(): void
    {
        // Margen 40% (costo = venta × 0,6): un 10% no lo rompe, un 45% sí.
        Repuesto::factory()->create(['taller_id' => $this->taller->id, 'nombre' => 'Filtro', 'precio_costo' => 6600, 'precio_venta' => 11000]);
        // Margen de 5%: cualquier descuento mayor a 5% lo deja bajo costo.
        Repuesto::factory()->create(['taller_id' => $this->taller->id, 'nombre' => 'Batería', 'precio_costo' => 95000, 'precio_venta' => 100000]);

        $this->actingAs($this->admin)->getJson(route('tipos-cliente.analizar', ['porcentaje' => 10]))
            ->assertOk()
            ->assertJsonPath('bajo_costo', 1)
            ->assertJsonPath('ejemplos.0.nombre', 'Batería')
            ->assertJsonPath('ejemplos.0.precio_con_descuento', 90000);

        $this->actingAs($this->admin)->getJson(route('tipos-cliente.analizar', ['porcentaje' => 45]))
            ->assertJsonPath('bajo_costo', 2);

        $this->actingAs($this->admin)->getJson(route('tipos-cliente.analizar', ['porcentaje' => 3]))
            ->assertJsonPath('bajo_costo', 0);

        $this->actingAs($this->admin)
            ->post(route('tipos-cliente.store'), ['nombre' => 'Precio distribuidor', 'descuento_repuestos_pct' => 10, 'activo' => '1'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('advertencia', 'Con 10.00% de descuento, 1 repuesto queda por debajo del costo para los clientes de este tipo.');
    }
}
