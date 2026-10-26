<?php

namespace Tests\Feature\Inventario;

use App\Models\Cliente;
use App\Models\DetalleOrden;
use App\Models\Moto;
use App\Models\OrdenServicio;
use App\Models\Repuesto;
use App\Models\Taller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsaTaller;
use Tests\TestCase;

class EliminarRepuestoTest extends TestCase
{
    use RefreshDatabase, UsaTaller;

    private Taller $taller;

    private User $admin;

    private Repuesto $repuesto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->taller = Taller::factory()->create();
        $this->admin = User::factory()->administradora()->deTaller($this->taller)->create();
        $this->repuesto = Repuesto::factory()->create([
            'taller_id' => $this->taller->id,
            'nombre' => 'Filtro de aceite K&N',
            'codigo_sku' => 'REP-FIL-001',
        ]);
    }

    public function test_exige_la_casilla_y_la_palabra_eliminar(): void
    {
        $casos = [
            [],
            ['confirmacion' => 'ELIMINAR'],
            ['confirmo' => '1'],
            ['confirmo' => '1', 'confirmacion' => 'eliminar'],
        ];

        foreach ($casos as $datos) {
            $this->actingAs($this->admin)
                ->from(route('inventario.edit', $this->repuesto))
                ->delete(route('inventario.destroy', $this->repuesto), $datos)
                ->assertSessionHasErrors();
        }

        $this->assertNotSoftDeleted($this->repuesto);
    }

    public function test_con_doble_confirmacion_hace_borrado_logico(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('inventario.destroy', $this->repuesto), ['confirmo' => '1', 'confirmacion' => 'ELIMINAR'])
            ->assertRedirect(route('inventario.index'))
            ->assertSessionHas('status');

        $this->assertSoftDeleted($this->repuesto);

        $this->enTaller($this->taller);
        $this->assertNull(Repuesto::find($this->repuesto->id), 'Un repuesto eliminado no debe aparecer ni venderse.');
    }

    public function test_la_factura_historica_sigue_mostrando_el_repuesto_eliminado(): void
    {
        $this->enTaller($this->taller);
        $cliente = Cliente::create(['nombre' => 'Cliente', 'telefono' => '3001234567', 'estado_cliente' => 'activo']);
        $moto = Moto::create(['placa' => 'ABC12D', 'marca' => 'Yamaha', 'modelo' => 'FZ', 'cliente_id' => $cliente->id]);
        $orden = OrdenServicio::create(['cliente_id' => $cliente->id, 'moto_id' => $moto->id, 'estado' => 'pagada']);
        $detalle = DetalleOrden::create([
            'orden_servicio_id' => $orden->id,
            'repuesto_id' => $this->repuesto->id,
            'cantidad' => 1,
            'precio_unitario' => 11000,
            'precio_unitario_original' => 11000,
            'subtotal' => 11000,
        ]);

        $this->repuesto->delete();

        $this->assertSame('Filtro de aceite K&N', $detalle->fresh()->repuesto->nombre);
    }

    public function test_el_sku_de_un_repuesto_eliminado_se_puede_reutilizar(): void
    {
        $this->repuesto->delete();

        $this->actingAs($this->admin)
            ->post(route('inventario.store'), [
                'nombre' => 'Filtro de aceite K&N nuevo',
                'codigo_sku' => 'REP-FIL-001',
                'precio_costo' => 6600,
                'precio_venta' => 11000,
                'stock_actual' => 5,
                'stock_minimo' => 2,
            ])
            ->assertSessionHasNoErrors();

        $this->enTaller($this->taller);
        $this->assertSame('Filtro de aceite K&N nuevo', Repuesto::where('codigo_sku', 'REP-FIL-001')->value('nombre'));
    }
}
