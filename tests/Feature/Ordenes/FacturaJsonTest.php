<?php

namespace Tests\Feature\Ordenes;

use App\Models\Cliente;
use App\Models\ConfiguracionTaller;
use App\Models\Mecanico;
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
 * JSON de factura para la IA: storage/app/facturas/{orden_id}.json.
 * Regla 3: nunca contiene el método de pago.
 */
class FacturaJsonTest extends TestCase
{
    use RefreshDatabase, UsaTaller;

    private Taller $taller;

    private User $admin;

    private Repuesto $filtro;

    private OrdenServicio $orden;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('facturas');

        $this->taller = Taller::factory()->create(['nombre' => 'Servimotos Fruna', 'nit' => '900123456-1']);
        $this->admin = User::factory()->administradora()->conPin()->deTaller($this->taller)->create();
        $this->filtro = Repuesto::factory()->create([
            'taller_id' => $this->taller->id,
            'nombre' => 'Filtro de aceite K&N',
            'codigo_sku' => 'FIL-001',
            'precio_costo' => 40000,
            'precio_venta' => 70000,
            'stock_actual' => 10,
        ]);

        $this->enTaller($this->taller);
        ConfiguracionTaller::updateOrCreate(['taller_id' => $this->taller->id], ['direccion' => 'Cra 10 # 20-30']);
        $vip = TipoCliente::where('slug', 'vip-flota')->firstOrFail();
        $cliente = Cliente::factory()->create([
            'taller_id' => $this->taller->id,
            'nombre' => 'Mensajería Rápida SAS',
            'cedula_nit' => '900555444-2',
            'tipo_cliente_id' => $vip->id,
        ]);
        $moto = Moto::factory()->create(['cliente_id' => $cliente->id, 'placa' => 'ABC12D', 'marca' => 'Bajaj', 'modelo' => 'Pulsar NS 200']);
        $mecanico = Mecanico::create(['nombre' => 'Carlos']);
        $this->orden = OrdenServicio::create([
            'cliente_id' => $cliente->id,
            'moto_id' => $moto->id,
            'mecanico_id' => $mecanico->id,
            'estado' => 'terminada',
            'descripcion_falla' => 'Ruido al frenar',
        ]);
        $this->enTaller(null);
    }

    private function liquidar(int $cantidad = 2): void
    {
        $this->actingAs($this->admin)->postJson(route('facturas.generar', $this->orden), [
            'items' => [['repuesto_id' => $this->filtro->id, 'cantidad' => $cantidad]],
            'mano_obra' => 37000,
            'observaciones_tecnicas' => 'Cambio de filtro y pastillas de freno.',
        ])->assertOk();
    }

    private function facturaJson(): array
    {
        return json_decode(Storage::disk('facturas')->get("{$this->orden->id}.json"), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_liquidar_genera_el_json_completo(): void
    {
        $this->liquidar(); // VIP 10 %: 2 × 63.000 = 126.000 + 37.000 = 163.000, IVA incluido

        Storage::disk('facturas')->assertExists("{$this->orden->id}.json");
        $json = $this->facturaJson();

        $this->assertSame('servimotos.factura.v1', $json['esquema']);
        $this->assertSame('COP', $json['moneda']);
        $this->assertSame(1, $json['numero_factura']);
        $this->assertSame($this->orden->id, $json['orden_id']);
        $this->assertStringEndsWith('-05:00', $json['fecha_emision']);

        $this->assertSame(['nombre' => 'Servimotos Fruna', 'nit' => '900123456-1', 'direccion' => 'Cra 10 # 20-30', 'telefono' => null], $json['taller']);
        $this->assertSame('Mensajería Rápida SAS', $json['cliente']['nombre']);
        $this->assertSame('900555444-2', $json['cliente']['cedula_nit']);
        $this->assertSame('VIP / Flota', $json['cliente']['tipo_cliente']);
        $this->assertSame('ABC12D', $json['moto']['placa']);
        $this->assertSame('Pulsar NS 200', $json['moto']['modelo']);
        $this->assertSame('Cambio de filtro y pastillas de freno.', $json['servicio']['observaciones_tecnicas']);
        $this->assertSame('Carlos', $json['servicio']['mecanico']);

        $this->assertSame([
            [
                'tipo' => 'repuesto',
                'repuesto_id' => $this->filtro->id,
                'sku' => 'FIL-001',
                'descripcion' => 'Filtro de aceite K&N',
                'cantidad' => 2,
                'precio_unitario_lista' => 70000,
                'descuento_pct' => 10.0,
                'precio_unitario' => 63000,
                'subtotal' => 126000,
            ],
            [
                'tipo' => 'mano_obra',
                'descripcion' => 'Mano de obra',
                'cantidad' => 1,
                'precio_unitario' => 37000,
                'subtotal' => 37000,
            ],
        ], $json['items']);

        $this->assertSame([
            'repuestos_precio_lista' => 140000,
            'descuento_repuestos' => 14000,
            'repuestos' => 126000,
            'mano_obra' => 37000,
            'subtotal' => 163000,
            'base_gravable' => 136975,
            'iva' => 26025,
            'tasa_iva_pct' => 19.0,
            'precios_incluyen_iva' => true,
            'total' => 163000,
        ], $json['totales']);
    }

    public function test_el_json_nunca_contiene_el_metodo_de_pago(): void
    {
        $this->liquidar();
        $this->actingAs($this->admin)
            ->patchJson(route('ordenes.pagar', $this->orden), ['metodo_pago' => 'daviplata'])
            ->assertOk();

        $contenido = Storage::disk('facturas')->get("{$this->orden->id}.json");

        $this->assertStringNotContainsString('metodo_pago', $contenido);
        $this->assertStringNotContainsStringIgnoringCase('daviplata', $contenido);
        $this->assertStringNotContainsStringIgnoringCase('pago', $contenido);
    }

    public function test_corregir_borra_el_json_y_reliquidar_lo_regenera_con_el_mismo_numero(): void
    {
        $this->liquidar(2);

        $this->actingAs($this->admin)->postJson(route('facturas.corregir', $this->orden), [
            'motivo' => 'Eran tres filtros, no dos.',
            'pin_autorizacion' => '482915',
        ])->assertOk();

        Storage::disk('facturas')->assertMissing("{$this->orden->id}.json");

        $this->liquidar(3);

        $json = $this->facturaJson();
        $this->assertSame(1, $json['numero_factura']);
        $this->assertSame(3, $json['items'][0]['cantidad']);
    }

    public function test_una_liquidacion_rechazada_no_deja_json(): void
    {
        $this->filtro->forceFill(['stock_actual' => 1])->saveQuietly();

        $this->actingAs($this->admin)->postJson(route('facturas.generar', $this->orden), [
            'items' => [['repuesto_id' => $this->filtro->id, 'cantidad' => 5]],
            'mano_obra' => 0,
            'observaciones_tecnicas' => 'Sin stock suficiente.',
        ])->assertStatus(422);

        Storage::disk('facturas')->assertMissing("{$this->orden->id}.json");
    }
}
