<?php

namespace Tests\Feature\Clientes;

use App\Models\Cliente;
use App\Models\DetalleOrden;
use App\Models\Moto;
use App\Models\OrdenServicio;
use App\Models\Repuesto;
use App\Models\Taller;
use App\Models\TipoCliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\UsaTaller;
use Tests\TestCase;

/**
 * Regla de negocio 8: el precio de los repuestos al liquidar lo pone SIEMPRE
 * el backend, desde precio_venta y el descuento del tipo de cliente.
 */
class LiquidacionConDescuentoTest extends TestCase
{
    use RefreshDatabase, UsaTaller;

    private Taller $taller;

    /** Quien emite la factura: la administradora. */
    private User $mecanico;

    private Repuesto $filtro;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('facturas');

        $this->taller = Taller::factory()->create();
        $this->mecanico = User::factory()->administradora()->deTaller($this->taller)->create(); // emite la factura
        $this->filtro = Repuesto::factory()->create([
            'taller_id' => $this->taller->id,
            'nombre' => 'Filtro de aceite K&N',
            'precio_costo' => 21000,
            'precio_venta' => 35000,
            'stock_actual' => 10,
        ]);
    }

    private function ordenDe(string $slugTipo): OrdenServicio
    {
        $this->enTaller($this->taller);
        $tipo = TipoCliente::where('slug', $slugTipo)->firstOrFail();
        $cliente = Cliente::factory()->create(['taller_id' => $this->taller->id, 'tipo_cliente_id' => $tipo->id]);
        $moto = Moto::factory()->create(['cliente_id' => $cliente->id]);
        $orden = OrdenServicio::create(['cliente_id' => $cliente->id, 'moto_id' => $moto->id, 'estado' => 'terminada']);
        $this->enTaller(null);

        return $orden;
    }

    private function liquidar(OrdenServicio $orden, array $item): DetalleOrden
    {
        $this->actingAs($this->mecanico)
            ->post(route('facturas.generar', $orden), [
                'items' => [[
                    'repuesto_id' => $this->filtro->id,
                    'cantidad' => 2,
                    ...$item,
                ]],
                'mano_obra' => 0,
                'observaciones_tecnicas' => 'Cambio de filtro de aceite.',
            ])
            ->assertRedirect(route('ordenes.show', $orden))
            ->assertSessionHasNoErrors();

        return DetalleOrden::withoutGlobalScopes()->where('orden_servicio_id', $orden->id)->sole();
    }

    public function test_cliente_vip_liquida_con_descuento_y_queda_el_snapshot(): void
    {
        $detalle = $this->liquidar($this->ordenDe('vip-flota'), []);

        $this->assertEquals(31500, $detalle->precio_unitario);
        $this->assertEquals(35000, $detalle->precio_unitario_original);
        $this->assertEquals(10, $detalle->descuento_aplicado_pct);
        $this->assertEquals(63000, $detalle->subtotal);
        $this->assertSame(8, $this->filtro->fresh()->stock_actual);
    }

    public function test_cliente_regular_paga_precio_de_lista(): void
    {
        $detalle = $this->liquidar($this->ordenDe('regular'), []);

        $this->assertEquals(35000, $detalle->precio_unitario);
        $this->assertEquals(35000, $detalle->precio_unitario_original);
        $this->assertEquals(0, $detalle->descuento_aplicado_pct);
    }

    public function test_un_precio_manipulado_en_el_request_se_ignora(): void
    {
        $detalle = $this->liquidar($this->ordenDe('vip-flota'), [
            'precio_unitario' => 1,
            'precio_unitario_original' => 1,
            'descuento_aplicado_pct' => 99,
            'subtotal' => 2,
        ]);

        $this->assertEquals(31500, $detalle->precio_unitario);
        $this->assertEquals(35000, $detalle->precio_unitario_original);
        $this->assertEquals(10, $detalle->descuento_aplicado_pct);
        $this->assertEquals(63000, $detalle->subtotal);
    }

    public function test_cliente_sin_tipo_usa_regular_como_respaldo(): void
    {
        $orden = $this->ordenDe('vip-flota');
        Cliente::withoutGlobalScopes()->whereKey($orden->cliente_id)->update(['tipo_cliente_id' => null]);

        $detalle = $this->liquidar($orden, []);

        $this->assertEquals(35000, $detalle->precio_unitario);
        $this->assertEquals(0, $detalle->descuento_aplicado_pct);
    }

    public function test_cambiar_el_descuento_despues_no_altera_facturas_ya_liquidadas(): void
    {
        $detalle = $this->liquidar($this->ordenDe('vip-flota'), []);

        TipoCliente::withoutGlobalScopes()
            ->where('taller_id', $this->taller->id)
            ->where('slug', 'vip-flota')
            ->update(['descuento_repuestos_pct' => 25]);

        $this->assertEquals(31500, $detalle->fresh()->precio_unitario);
        $this->assertEquals(10, $detalle->fresh()->descuento_aplicado_pct);
    }
}
