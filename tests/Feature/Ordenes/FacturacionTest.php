<?php

namespace Tests\Feature\Ordenes;

use App\Models\AuditoriaAutorizacion;
use App\Models\Cliente;
use App\Models\ConfiguracionTaller;
use App\Models\DetalleOrden;
use App\Models\Mecanico;
use App\Models\Moto;
use App\Models\OrdenEstadoHistorial;
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
 * Bloque 3: liquidación (sin doble descuento de stock), corrección
 * reversible con PIN, consecutivo de factura, IVA configurable y pago.
 */
class FacturacionTest extends TestCase
{
    use RefreshDatabase, UsaTaller;

    private Taller $taller;

    private User $admin;

    private User $mecanicoUser;

    private Repuesto $filtro;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('facturas');

        $this->taller = Taller::factory()->create();
        $this->admin = User::factory()->administradora()->conPin()->deTaller($this->taller)->create();
        $this->mecanicoUser = User::factory()->mecanico()->deTaller($this->taller)->create();
        $this->filtro = Repuesto::factory()->create([
            'taller_id' => $this->taller->id,
            'precio_costo' => 40000,
            'precio_venta' => 70000,
            'stock_actual' => 10,
        ]);
    }

    private function orden(string $estado = 'terminada', string $tipo = 'regular', ?Taller $taller = null): OrdenServicio
    {
        $taller ??= $this->taller;
        $this->enTaller($taller);
        $tipoCliente = TipoCliente::where('slug', $tipo)->firstOrFail();
        $cliente = Cliente::factory()->create(['taller_id' => $taller->id, 'tipo_cliente_id' => $tipoCliente->id]);
        $moto = Moto::factory()->create(['cliente_id' => $cliente->id]);
        $orden = OrdenServicio::create(['cliente_id' => $cliente->id, 'moto_id' => $moto->id, 'estado' => $estado]);
        $this->enTaller(null);

        return $orden;
    }

    private function mecanico(array $atributos = []): Mecanico
    {
        $this->enTaller($this->taller);
        $mecanico = Mecanico::create(['nombre' => 'Carlos', ...$atributos]);
        $this->enTaller(null);

        return $mecanico;
    }

    private function liquidar(OrdenServicio $orden, int $cantidad = 2, float $manoObra = 30000, ?User $user = null)
    {
        // Emitir la factura es de la administradora (el mecánico solo registra el trabajo).
        return $this->actingAs($user ?? $this->admin)->postJson(route('facturas.generar', $orden), [
            'items' => [['repuesto_id' => $this->filtro->id, 'cantidad' => $cantidad]],
            'mano_obra' => $manoObra,
            'observaciones_tecnicas' => 'Cambio de filtro y ajuste de cadena.',
        ]);
    }

    private function corregir(OrdenServicio $orden, ?User $user = null, string $pin = '482915')
    {
        return $this->actingAs($user ?? $this->admin)->postJson(route('facturas.corregir', $orden), [
            'motivo' => 'Se facturó una cantidad equivocada de filtros.',
            'pin_autorizacion' => $pin,
        ]);
    }

    private function stock(): int
    {
        return $this->filtro->fresh()->stock_actual;
    }

    private function detalles(OrdenServicio $orden)
    {
        return DetalleOrden::withoutGlobalScopes()->where('orden_servicio_id', $orden->id)->get();
    }

    private function historial(OrdenServicio $orden)
    {
        return OrdenEstadoHistorial::withoutGlobalScopes()->where('orden_servicio_id', $orden->id)->orderBy('id')->get();
    }

    // --- Bug de doble descuento de stock ---

    public function test_liquidar_dos_veces_no_descuenta_el_stock_dos_veces(): void
    {
        $orden = $this->orden();

        $this->liquidar($orden)->assertOk();
        $this->assertSame(8, $this->stock());

        $this->liquidar($orden)->assertStatus(422)
            ->assertJson(['message' => 'Solo se puede liquidar una orden en estado "Terminada".']);

        $this->assertSame(8, $this->stock());
        $this->assertCount(1, $this->detalles($orden));
    }

    public function test_corregir_repone_el_stock_y_reliquidar_descuenta_solo_lo_nuevo(): void
    {
        $orden = $this->orden();
        $this->liquidar($orden, cantidad: 2)->assertOk();
        $numero = $orden->fresh()->numero_factura;

        $this->corregir($orden)->assertOk()->assertJson(['redirect' => route('ordenes.show', [$orden, 'liquidar' => 1])]);

        $orden->refresh();
        $this->assertSame(10, $this->stock());
        $this->assertCount(0, $this->detalles($orden));
        $this->assertSame('terminada', $orden->estado);
        $this->assertEquals(0, $orden->total);
        $this->assertNull($orden->liquidada_at);

        $correccion = $this->historial($orden)->firstWhere('evento', 'correccion');
        $this->assertSame('listo_para_reclamar', $correccion->estado_anterior);
        $this->assertSame('terminada', $correccion->estado_nuevo);
        $this->assertSame('Se facturó una cantidad equivocada de filtros.', $correccion->observaciones);
        $this->assertSame($this->admin->id, $correccion->user_id);
        $this->assertTrue(AuditoriaAutorizacion::withoutGlobalScopes()
            ->where('accion', 'factura.corregir')->where('modelo_id', $orden->id)->exists());

        $this->liquidar($orden, cantidad: 3)->assertOk();

        $this->assertSame(7, $this->stock());
        $this->assertSame(3, $this->detalles($orden)->sole()->cantidad);
        $this->assertSame($numero, $orden->fresh()->numero_factura);
        $this->assertSame(1, OrdenServicio::withoutGlobalScopes()->withCount('correcciones')->find($orden->id)->correcciones_count);
    }

    public function test_el_mismo_repuesto_en_dos_lineas_se_une_en_un_detalle(): void
    {
        $orden = $this->orden();

        $this->actingAs($this->admin)->postJson(route('facturas.generar', $orden), [
            'items' => [
                ['repuesto_id' => $this->filtro->id, 'cantidad' => 1],
                ['repuesto_id' => $this->filtro->id, 'cantidad' => 2],
            ],
            'mano_obra' => 0,
            'observaciones_tecnicas' => 'Tres filtros.',
        ])->assertOk();

        $this->assertSame(3, $this->detalles($orden)->sole()->cantidad);
        $this->assertSame(7, $this->stock());
    }

    // --- Corrección: permisos y PIN ---

    public function test_corregir_una_orden_pagada_da_403_con_mensaje(): void
    {
        $orden = $this->orden();
        $this->liquidar($orden)->assertOk();
        OrdenServicio::withoutGlobalScopes()->whereKey($orden->id)->update(['estado' => 'pagada']);

        $this->corregir($orden)->assertForbidden()
            ->assertJson(['message' => 'Orden ya pagada. Para corregir, cree una nueva orden.']);
        $this->assertSame(8, $this->stock());
    }

    public function test_el_mecanico_no_puede_corregir(): void
    {
        $orden = $this->orden();
        $this->liquidar($orden)->assertOk();

        $this->corregir($orden, $this->mecanicoUser)->assertForbidden();
        $this->assertSame(8, $this->stock());
    }

    public function test_pin_incorrecto_no_corrige_ni_audita(): void
    {
        $orden = $this->orden();
        $this->liquidar($orden)->assertOk();

        $this->corregir($orden, pin: '000000')->assertForbidden();

        $this->assertSame(8, $this->stock());
        $this->assertSame('listo_para_reclamar', $orden->fresh()->estado);
        $this->assertFalse($this->historial($orden)->contains('evento', 'correccion'));
        $this->assertSame(0, AuditoriaAutorizacion::withoutGlobalScopes()->count());
    }

    public function test_corregir_exige_motivo(): void
    {
        $orden = $this->orden();
        $this->liquidar($orden)->assertOk();

        $this->actingAs($this->admin)->postJson(route('facturas.corregir', $orden), ['pin_autorizacion' => '482915'])
            ->assertJsonValidationErrors('motivo');
    }

    // --- Liquidación: validación, consecutivo e IVA ---

    public function test_observaciones_tecnicas_y_mano_obra_son_obligatorias(): void
    {
        $orden = $this->orden();

        $this->actingAs($this->admin)->postJson(route('facturas.generar', $orden), [
            'items' => [['repuesto_id' => $this->filtro->id, 'cantidad' => 1]],
        ])->assertJsonValidationErrors(['observaciones_tecnicas', 'mano_obra']);

        $this->assertSame(10, $this->stock());
    }

    public function test_sin_repuestos_ni_mano_de_obra_no_se_liquida(): void
    {
        $this->actingAs($this->admin)->postJson(route('facturas.generar', $this->orden()), [
            'mano_obra' => 0,
            'observaciones_tecnicas' => 'Nada que cobrar.',
        ])->assertJsonValidationErrors('items');
    }

    public function test_solo_mano_de_obra_si_se_liquida(): void
    {
        $orden = $this->orden();

        $this->actingAs($this->admin)->postJson(route('facturas.generar', $orden), [
            'mano_obra' => 25000,
            'observaciones_tecnicas' => 'Ajuste de frenos.',
        ])->assertOk();

        $this->assertEquals(25000, $orden->fresh()->total);
    }

    public function test_repuesto_de_otro_taller_se_rechaza(): void
    {
        $otro = Repuesto::factory()->create(['taller_id' => Taller::factory()->create()->id, 'stock_actual' => 5]);

        $this->actingAs($this->admin)->postJson(route('facturas.generar', $this->orden()), [
            'items' => [['repuesto_id' => $otro->id, 'cantidad' => 1]],
            'mano_obra' => 0,
            'observaciones_tecnicas' => 'Intento con repuesto ajeno.',
        ])->assertJsonValidationErrors('items.0.repuesto_id');

        $this->assertSame(5, $otro->fresh()->stock_actual);
    }

    public function test_numero_de_factura_consecutivo_por_taller(): void
    {
        // Antes de autenticarse: el modelo bloquea precios sin permiso de gestión.
        $otroTaller = Taller::factory()->create();
        $repuesto = Repuesto::factory()->create(['taller_id' => $otroTaller->id, 'stock_actual' => 5]);

        $a = $this->orden();
        $b = $this->orden();
        $this->liquidar($a, 1)->assertOk();
        $this->liquidar($b, 1)->assertOk();

        $this->assertSame(1, $a->fresh()->numero_factura);
        $this->assertSame(2, $b->fresh()->numero_factura);
        $c = $this->orden(taller: $otroTaller);

        $this->actingAs(User::factory()->administradora()->deTaller($otroTaller)->create())
            ->postJson(route('facturas.generar', $c), [
                'items' => [['repuesto_id' => $repuesto->id, 'cantidad' => 1]],
                'mano_obra' => 0,
                'observaciones_tecnicas' => 'Otro taller.',
            ])->assertOk();

        $this->assertSame(1, $c->fresh()->numero_factura);
    }

    public function test_iva_incluido_se_desglosa_sin_cambiar_el_total(): void
    {
        $orden = $this->orden();
        $this->liquidar($orden, cantidad: 1, manoObra: 30000)->assertOk(); // 70.000 + 30.000

        $orden->refresh();
        $this->assertEquals(70000, $orden->subtotal_repuestos);
        $this->assertEquals(30000, $orden->mano_obra);
        $this->assertEquals(84034, $orden->base_gravable);
        $this->assertEquals(15966, $orden->iva);
        $this->assertEquals(100000, $orden->total);
        $this->assertEquals(19, $orden->tasa_iva_aplicada);
        $this->assertTrue($orden->precios_incluian_iva);
    }

    public function test_iva_no_incluido_se_suma_encima_despues_del_descuento(): void
    {
        ConfiguracionTaller::withoutGlobalScopes()->updateOrCreate(
            ['taller_id' => $this->taller->id],
            ['precios_incluyen_iva' => false],
        );
        $orden = $this->orden(tipo: 'vip-flota'); // 10 %: 70.000 → 63.000

        $this->liquidar($orden, cantidad: 1, manoObra: 37000)->assertOk(); // 63.000 + 37.000

        $orden->refresh();
        $this->assertEquals(7000, $orden->descuento_total);
        $this->assertEquals(100000, $orden->base_gravable);
        $this->assertEquals(19000, $orden->iva);
        $this->assertEquals(119000, $orden->total);
        $this->assertFalse($orden->precios_incluian_iva);
    }

    public function test_cambiar_el_iva_despues_no_altera_la_factura_emitida(): void
    {
        $orden = $this->orden();
        $this->liquidar($orden, cantidad: 1, manoObra: 30000)->assertOk();

        ConfiguracionTaller::withoutGlobalScopes()->where('taller_id', $this->taller->id)
            ->update(['tasa_iva' => 5, 'precios_incluyen_iva' => false]);

        $this->assertEquals(100000, $orden->fresh()->total);
        $this->assertEquals(15966, $orden->fresh()->iva);
    }

    // --- Pago ---

    public function test_admin_marca_pagada_con_metodo_interno(): void
    {
        $orden = $this->orden();
        $this->liquidar($orden)->assertOk();

        $this->actingAs($this->admin)->patchJson(route('ordenes.pagar', $orden), ['metodo_pago' => 'nequi'])
            ->assertOk()
            ->assertJsonMissingPath('orden.metodo_pago');

        $orden->refresh();
        $this->assertSame('pagada', $orden->estado);
        $this->assertSame('nequi', $orden->metodo_pago);
        $this->assertNotNull($orden->pagada_at);
        $this->assertArrayNotHasKey('metodo_pago', $orden->toArray());
        $this->assertSame('pago', $this->historial($orden)->last()->evento);
    }

    public function test_el_mecanico_no_puede_marcar_pagada(): void
    {
        $orden = $this->orden();
        $this->liquidar($orden)->assertOk();

        $this->actingAs($this->mecanicoUser)->patchJson(route('ordenes.pagar', $orden), ['metodo_pago' => 'efectivo'])
            ->assertForbidden();
        $this->assertSame('listo_para_reclamar', $orden->fresh()->estado);
    }

    public function test_metodo_de_pago_invalido_se_rechaza(): void
    {
        $orden = $this->orden();
        $this->liquidar($orden)->assertOk();

        $this->actingAs($this->admin)->patchJson(route('ordenes.pagar', $orden), ['metodo_pago' => 'transferencia_bancolombia'])
            ->assertJsonValidationErrors('metodo_pago');
    }

    // --- Cambios de estado (Kanban) ---

    public function test_pasar_a_revision_exige_mecanico_y_observaciones(): void
    {
        $orden = $this->orden('en_espera_revision');

        $this->actingAs($this->mecanicoUser)->patchJson(route('ordenes.estado', $orden), ['estado' => 'en_revision'])
            ->assertJsonValidationErrors(['mecanico_id', 'observaciones']);
        $this->assertSame('en_espera_revision', $orden->fresh()->estado);
    }

    public function test_pasar_a_revision_asigna_mecanico_y_queda_en_historial(): void
    {
        $orden = $this->orden('en_espera_revision');
        $mecanico = $this->mecanico();

        $this->actingAs($this->mecanicoUser)->patchJson(route('ordenes.estado', $orden), [
            'estado' => 'en_revision',
            'mecanico_id' => $mecanico->id,
            'observaciones' => 'Ruido en el motor al acelerar.',
        ])->assertOk()->assertJsonPath('orden.mecanico.nombre', 'Carlos');

        $this->assertSame($mecanico->id, $orden->fresh()->mecanico_id);

        $fila = $this->historial($orden)->sole();
        $this->assertSame('en_espera_revision', $fila->estado_anterior);
        $this->assertSame('en_revision', $fila->estado_nuevo);
        $this->assertSame('cambio_estado', $fila->evento);
        $this->assertSame($mecanico->id, $fila->mecanico_id);
        $this->assertSame($this->mecanicoUser->id, $fila->user_id);
        $this->assertSame('Ruido en el motor al acelerar.', $fila->observaciones);
    }

    public function test_un_mecanico_inactivo_no_se_puede_asignar(): void
    {
        $orden = $this->orden('en_espera_revision');
        $inactivo = $this->mecanico(['activo' => false]);

        $this->actingAs($this->mecanicoUser)->patchJson(route('ordenes.estado', $orden), [
            'estado' => 'en_revision',
            'mecanico_id' => $inactivo->id,
            'observaciones' => 'Revisión.',
        ])->assertJsonValidationErrors('mecanico_id');
    }

    public function test_no_se_llega_a_listo_para_reclamar_ni_a_pagada_arrastrando(): void
    {
        $orden = $this->orden('terminada');

        $this->actingAs($this->admin)->patchJson(route('ordenes.estado', $orden), ['estado' => 'listo_para_reclamar'])
            ->assertStatus(422)->assertJson(['message' => 'Para pasar a "Listo para reclamar" hay que liquidar la orden.']);

        $this->actingAs($this->admin)->patchJson(route('ordenes.estado', $orden), ['estado' => 'pagada'])
            ->assertJsonValidationErrors('estado');

        $this->assertSame('terminada', $orden->fresh()->estado);
    }

    public function test_una_orden_liquidada_no_sale_arrastrando_y_conserva_su_stock(): void
    {
        $orden = $this->orden();
        $this->liquidar($orden)->assertOk();

        $this->actingAs($this->admin)->patchJson(route('ordenes.estado', $orden), ['estado' => 'terminada'])
            ->assertStatus(422)->assertJson(['message' => 'La orden ya está liquidada. Para modificarla, corrige la factura.']);

        $this->assertSame('listo_para_reclamar', $orden->fresh()->estado);
        $this->assertSame(8, $this->stock());
    }
}
