<?php

namespace Tests\Feature\Ordenes;

use App\Models\Cliente;
use App\Models\DetalleOrden;
use App\Models\Mecanico;
use App\Models\Moto;
use App\Models\OrdenEstadoHistorial;
use App\Models\OrdenServicio;
use App\Models\Repuesto;
use App\Models\Taller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\UsaTaller;
use Tests\TestCase;

/**
 * Liquidación en dos pasos: el mecánico registra el trabajo (repuestos,
 * cantidades, observaciones) sin fijar dinero; la administradora fija la mano
 * de obra y emite la factura. El stock nunca se descuenta dos veces.
 */
class LiquidacionDosPasosTest extends TestCase
{
    use RefreshDatabase, UsaTaller;

    private Taller $taller;

    private User $admin;

    private User $mecanico;

    private Repuesto $filtro;

    private Repuesto $bujia;

    private OrdenServicio $orden;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('facturas');

        $this->taller = Taller::factory()->create();
        $this->admin = User::factory()->administradora()->deTaller($this->taller)->create();
        $this->mecanico = User::factory()->mecanico()->deTaller($this->taller)->create();
        $this->filtro = Repuesto::factory()->create(['taller_id' => $this->taller->id, 'nombre' => 'Filtro de aceite', 'precio_costo' => 40000, 'precio_venta' => 70000, 'stock_actual' => 10]);
        $this->bujia = Repuesto::factory()->create(['taller_id' => $this->taller->id, 'nombre' => 'Bujía NGK', 'precio_costo' => 10000, 'precio_venta' => 20000, 'stock_actual' => 10]);

        $this->enTaller($this->taller);
        $cliente = Cliente::factory()->create(['taller_id' => $this->taller->id]);
        $moto = Moto::factory()->create(['cliente_id' => $cliente->id, 'placa' => 'ABC12D']);
        $carlos = Mecanico::create(['nombre' => 'Carlos', 'user_id' => $this->mecanico->id]);
        $this->orden = OrdenServicio::create(['cliente_id' => $cliente->id, 'moto_id' => $moto->id, 'mecanico_id' => $carlos->id, 'estado' => 'terminada']);
        $this->enTaller(null);
    }

    private function registrar(array $items, ?User $user = null, array $extra = [])
    {
        return $this->actingAs($user ?? $this->mecanico)->postJson(route('ordenes.trabajo', $this->orden), [
            'items' => $items,
            'observaciones_tecnicas' => 'Cambio de filtro y bujía.',
            ...$extra,
        ]);
    }

    private function emitir(array $items, float $manoObra = 30000)
    {
        return $this->actingAs($this->admin)->postJson(route('facturas.generar', $this->orden), [
            'items' => $items,
            'mano_obra' => $manoObra,
            'observaciones_tecnicas' => 'Cambio de filtro y bujía.',
        ]);
    }

    private function stock(Repuesto $repuesto): int
    {
        return $repuesto->fresh()->stock_actual;
    }

    private function detalles()
    {
        return DetalleOrden::withoutGlobalScopes()->where('orden_servicio_id', $this->orden->id)->orderBy('repuesto_id')->get();
    }

    public function test_el_mecanico_registra_el_trabajo_sin_emitir_factura(): void
    {
        $this->registrar([['repuesto_id' => $this->filtro->id, 'cantidad' => 2]])
            ->assertOk()
            ->assertJsonPath('orden.estado', 'terminada')
            ->assertJsonPath('orden.trabajo_registrado', true)
            ->assertJsonPath('orden.total', null)
            ->assertJsonPath('orden.repuestos.0.cantidad', 2)
            ->assertJsonMissingPath('orden.repuestos.0.precio_venta');

        $orden = $this->orden->fresh();
        $this->assertSame('terminada', $orden->estado);
        $this->assertNull($orden->numero_factura);
        $this->assertNull($orden->liquidada_at);
        $this->assertEquals(0, $orden->mano_obra);
        $this->assertSame('Cambio de filtro y bujía.', $orden->observaciones_tecnicas);
        $this->assertSame(8, $this->stock($this->filtro));
        Storage::disk('facturas')->assertMissing("{$this->orden->id}.json");

        $fila = OrdenEstadoHistorial::withoutGlobalScopes()->where('orden_servicio_id', $this->orden->id)->sole();
        $this->assertSame('liquidacion', $fila->evento);
        $this->assertSame('terminada', $fila->estado_nuevo);
        $this->assertSame('Trabajo registrado: 1 repuesto. Pendiente de factura.', $fila->observaciones);
    }

    public function test_el_mecanico_no_fija_dinero_aunque_lo_envie(): void
    {
        $this->registrar([['repuesto_id' => $this->filtro->id, 'cantidad' => 1, 'precio_unitario' => 1]], extra: ['mano_obra' => 999999, 'total' => 1])
            ->assertOk();

        $this->assertEquals(0, $this->orden->fresh()->mano_obra);
        $this->assertEquals(70000, $this->detalles()->sole()->precio_unitario);
    }

    public function test_editar_el_trabajo_no_descuenta_stock_dos_veces(): void
    {
        $this->registrar([['repuesto_id' => $this->filtro->id, 'cantidad' => 2]])->assertOk();
        $this->registrar([['repuesto_id' => $this->filtro->id, 'cantidad' => 3]])->assertOk();
        $this->registrar([['repuesto_id' => $this->bujia->id, 'cantidad' => 1]])->assertOk();

        $this->assertSame(10, $this->stock($this->filtro));
        $this->assertSame(9, $this->stock($this->bujia));
        $this->assertSame([$this->bujia->id], $this->detalles()->pluck('repuesto_id')->all());
    }

    public function test_el_mecanico_no_puede_emitir_la_factura(): void
    {
        $this->actingAs($this->mecanico)->postJson(route('facturas.generar', $this->orden), [
            'items' => [], 'mano_obra' => 30000, 'observaciones_tecnicas' => 'Intento de emitir.',
        ])->assertForbidden();

        $this->assertSame('terminada', $this->orden->fresh()->estado);
    }

    public function test_la_admin_emite_sobre_lo_registrado_sin_mover_el_stock(): void
    {
        $this->registrar([['repuesto_id' => $this->filtro->id, 'cantidad' => 2]])->assertOk();

        $this->emitir([['repuesto_id' => $this->filtro->id, 'cantidad' => 2]], 30000)
            ->assertOk()
            ->assertJsonPath('orden.estado', 'listo_para_reclamar')
            ->assertJsonPath('orden.numero_factura', 1)
            ->assertJsonPath('orden.total', 170000);

        $this->assertSame(8, $this->stock($this->filtro));
        $this->assertSame(2, $this->detalles()->sole()->cantidad);
        $this->assertEquals(30000, $this->orden->fresh()->mano_obra);
        Storage::disk('facturas')->assertExists("{$this->orden->id}.json");
    }

    public function test_la_admin_puede_ajustar_los_repuestos_al_emitir(): void
    {
        $this->registrar([
            ['repuesto_id' => $this->filtro->id, 'cantidad' => 2],
            ['repuesto_id' => $this->bujia->id, 'cantidad' => 1],
        ])->assertOk();

        $this->emitir([['repuesto_id' => $this->filtro->id, 'cantidad' => 1]])->assertOk();

        $this->assertSame(9, $this->stock($this->filtro));
        $this->assertSame(10, $this->stock($this->bujia));
        $this->assertSame([$this->filtro->id], $this->detalles()->pluck('repuesto_id')->all());
    }

    public function test_al_emitir_se_usa_el_precio_vigente(): void
    {
        $this->registrar([['repuesto_id' => $this->filtro->id, 'cantidad' => 1]])->assertOk();
        Repuesto::withoutGlobalScopes()->whereKey($this->filtro->id)->update(['precio_venta' => 75000]);

        $this->emitir([['repuesto_id' => $this->filtro->id, 'cantidad' => 1]], 0)->assertOk();

        $this->assertEquals(75000, $this->detalles()->sole()->precio_unitario);
        $this->assertEquals(75000, $this->orden->fresh()->total);
    }

    public function test_no_se_registra_trabajo_en_una_orden_facturada(): void
    {
        $this->emitir([['repuesto_id' => $this->filtro->id, 'cantidad' => 1]])->assertOk();

        $this->registrar([['repuesto_id' => $this->filtro->id, 'cantidad' => 5]])
            ->assertStatus(422)
            ->assertJson(['message' => 'Solo se puede liquidar una orden en estado "Terminada".']);
        $this->assertSame(9, $this->stock($this->filtro));
    }

    public function test_observaciones_obligatorias_al_registrar(): void
    {
        $this->actingAs($this->mecanico)->postJson(route('ordenes.trabajo', $this->orden), ['items' => []])
            ->assertJsonValidationErrors('observaciones_tecnicas');
    }

    public function test_cada_rol_ve_su_boton_en_el_kanban(): void
    {
        $this->actingAs($this->mecanico)->get(route('ordenes.kanban'))
            ->assertOk()
            ->assertSee('Registrar trabajo')
            ->assertDontSee('Emitir factura')
            ->assertDontSee('id="mano-obra"', false);

        $this->actingAs($this->admin)->get(route('ordenes.kanban'))
            ->assertOk()
            ->assertSee('Emitir factura')
            ->assertSee('id="mano-obra"', false);
    }

    public function test_detalle_y_mi_dia_muestran_el_trabajo_registrado(): void
    {
        $this->actingAs($this->mecanico)->get(route('mi-dia'))->assertOk()->assertSee('Falta registrar el trabajo');

        $this->registrar([['repuesto_id' => $this->filtro->id, 'cantidad' => 2]])->assertOk();

        $this->actingAs($this->mecanico)->get(route('mi-dia'))->assertOk()->assertSee('Trabajo registrado');
        $this->actingAs($this->mecanico)->get(route('ordenes.show', $this->orden))
            ->assertOk()
            ->assertSee('Trabajo registrado')
            ->assertSee('Filtro de aceite')
            ->assertSee('Editar trabajo')
            ->assertDontSee('70.000');
        $this->actingAs($this->admin)->get(route('ordenes.show', $this->orden))
            ->assertOk()
            ->assertSee('Pendiente: fijar mano de obra y emitir la factura')
            ->assertSee('Emitir factura');
    }
}
