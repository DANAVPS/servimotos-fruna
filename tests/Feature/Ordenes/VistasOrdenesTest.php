<?php

namespace Tests\Feature\Ordenes;

use App\Models\Cliente;
use App\Models\Moto;
use App\Models\OrdenServicio;
use App\Models\Repuesto;
use App\Models\Taller;
use App\Models\TipoCliente;
use App\Models\User;
use App\Services\FacturaService;
use App\Support\TarjetaOrden;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\UsaTaller;
use Tests\TestCase;

/**
 * Bloque 3, frontend: Kanban, detalle de la orden y factura PDF.
 */
class VistasOrdenesTest extends TestCase
{
    use RefreshDatabase, UsaTaller;

    private Taller $taller;

    private User $admin;

    private User $mecanico;

    private Repuesto $filtro;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('facturas');

        $this->taller = Taller::factory()->create(['nombre' => 'Servimotos Fruna']);
        $this->admin = User::factory()->administradora()->conPin()->deTaller($this->taller)->create();
        $this->mecanico = User::factory()->mecanico()->deTaller($this->taller)->create();
        $this->filtro = Repuesto::factory()->create([
            'taller_id' => $this->taller->id,
            'nombre' => 'Filtro de aceite K&N',
            'precio_costo' => 40000,
            'precio_venta' => 70000,
            'stock_actual' => 10,
        ]);
    }

    private function orden(string $estado = 'terminada', string $tipo = 'regular', array $atributos = []): OrdenServicio
    {
        $this->enTaller($this->taller);
        $tipoCliente = TipoCliente::where('slug', $tipo)->firstOrFail();
        $cliente = Cliente::factory()->create(['taller_id' => $this->taller->id, 'tipo_cliente_id' => $tipoCliente->id, 'nombre' => 'Andrés Gómez']);
        $moto = Moto::factory()->create(['cliente_id' => $cliente->id, 'placa' => 'ABC12D']);
        $orden = OrdenServicio::create(['cliente_id' => $cliente->id, 'moto_id' => $moto->id, 'estado' => $estado, ...$atributos]);
        $this->enTaller(null);

        return $orden;
    }

    private function liquidada(string $tipo = 'regular'): OrdenServicio
    {
        $orden = $this->orden('terminada', $tipo);

        $this->actingAs($this->admin)->postJson(route('facturas.generar', $orden), [
            'items' => [['repuesto_id' => $this->filtro->id, 'cantidad' => 1]],
            'mano_obra' => 30000,
            'observaciones_tecnicas' => 'Se cambió el filtro y se ajustó la cadena.',
        ])->assertOk();

        return $orden->fresh();
    }

    // --- Kanban ---

    public function test_el_kanban_carga_las_tarjetas_con_semaforo(): void
    {
        $this->orden('en_espera_revision', atributos: ['fecha_hora_ingreso' => now()->subDays(6)]);

        $this->actingAs($this->mecanico)->get(route('ordenes.kanban'))
            ->assertOk()
            ->assertSee('ABC12D')
            ->assertViewHas('tarjetas', fn ($tarjetas) => $tarjetas->sole()['semaforo'] === 'rojo' && $tarjetas->sole()['dias'] === 6)
            ->assertSee('+ Crear orden manual')
            ->assertDontSee('metodo_pago');
    }

    public function test_la_tarjeta_respeta_los_umbrales_configurados(): void
    {
        $orden = $this->orden('en_revision', atributos: ['fecha_hora_ingreso' => now()->subDays(3)]);
        $config = TarjetaOrden::configuracionDe($this->taller->id);

        $this->assertSame('amarillo', TarjetaOrden::datos($orden, $config, $this->admin)['semaforo']);

        $config->dias_alerta_amarilla = 4;
        $config->dias_alerta_roja = 8;
        $this->assertSame('verde', TarjetaOrden::datos($orden, $config, $this->admin)['semaforo']);
    }

    public function test_la_respuesta_de_cambio_de_estado_trae_la_tarjeta_completa(): void
    {
        $orden = $this->orden('reparacion');

        $this->actingAs($this->mecanico)->patchJson(route('ordenes.estado', $orden), ['estado' => 'terminada'])
            ->assertOk()
            ->assertJsonPath('orden.estado', 'terminada')
            ->assertJsonPath('orden.placa', 'ABC12D')
            ->assertJsonPath('orden.semaforo', 'verde')
            ->assertJsonMissingPath('orden.metodo_pago');
    }

    // --- Liquidación ---

    public function test_la_url_de_liquidar_abre_el_modal_en_el_detalle(): void
    {
        $orden = $this->orden('terminada');

        $this->actingAs($this->mecanico)->get(route('facturas.liquidar', $orden))
            ->assertRedirect(route('ordenes.show', [$orden, 'liquidar' => 1]));

        $this->actingAs($this->mecanico)->get(route('ordenes.show', [$orden, 'liquidar' => 1]))
            ->assertOk()
            ->assertSee("new CustomEvent('abrir-liquidacion'", false)
            ->assertSee('Registrar trabajo')
            ->assertDontSee('Emitir factura');
    }

    public function test_la_liquidacion_responde_la_tarjeta_para_el_kanban(): void
    {
        $orden = $this->orden('terminada', 'vip-flota');

        $this->actingAs($this->admin)->postJson(route('facturas.generar', $orden), [
            'items' => [['repuesto_id' => $this->filtro->id, 'cantidad' => 1]],
            'mano_obra' => 0,
            'observaciones_tecnicas' => 'Cambio de filtro.',
        ])->assertOk()
            ->assertJsonPath('orden.estado', 'listo_para_reclamar')
            ->assertJsonPath('orden.numero_factura', 1)
            ->assertJsonPath('orden.descuento_pct', 10);
    }

    // --- Detalle ---

    public function test_detalle_listo_para_reclamar_muestra_acciones_de_admin(): void
    {
        $orden = $this->liquidada();

        $this->actingAs($this->admin)->get(route('ordenes.show', $orden))
            ->assertOk()
            ->assertSee('Marcar como pagada')
            ->assertSee('Corregir factura')
            ->assertSee('Se cambió el filtro y se ajustó la cadena.')
            ->assertSee('Liquidada · factura emitida');

        $this->actingAs($this->mecanico)->get(route('ordenes.show', $orden))
            ->assertOk()
            ->assertDontSee('Marcar como pagada')
            ->assertDontSee('Corregir factura');
    }

    public function test_detalle_pagada_muestra_el_aviso_y_el_metodo_solo_a_la_admin(): void
    {
        $orden = $this->liquidada();
        $this->actingAs($this->admin)->patchJson(route('ordenes.pagar', $orden), ['metodo_pago' => 'nequi'])->assertOk();

        $this->actingAs($this->admin)->get(route('ordenes.show', $orden))
            ->assertOk()
            ->assertSee('Orden ya pagada.')
            ->assertSee('Para corregir, cree una nueva orden.')
            ->assertSee('Método (interno): Nequi')
            ->assertDontSee('Corregir factura');

        $this->actingAs($this->mecanico)->get(route('ordenes.show', $orden))
            ->assertOk()
            ->assertSee('Orden ya pagada.')
            ->assertDontSee('Nequi');
    }

    public function test_tras_corregir_vuelve_al_detalle_con_el_modal_y_los_datos_previos(): void
    {
        $orden = $this->liquidada();

        $this->actingAs($this->admin)->postJson(route('facturas.corregir', $orden), [
            'motivo' => 'Se facturó un filtro de más.',
            'pin_autorizacion' => '482915',
        ])->assertOk();

        $this->actingAs($this->admin)->get(route('ordenes.show', [$orden, 'liquidar' => 1]))
            ->assertOk()
            ->assertSee('Emitir factura de nuevo')
            ->assertSee('Corregida 1 vez')
            ->assertSee('Se facturó un filtro de más.')
            ->assertViewHas('tarjeta', fn ($t) => $t['mano_obra'] === 30000.0
                && $t['observaciones_tecnicas'] === 'Se cambió el filtro y se ajustó la cadena.'
                && $t['numero_factura'] === 1);
    }

    // --- PDF ---

    public function test_pdf_de_una_orden_sin_liquidar_es_404(): void
    {
        $this->actingAs($this->admin)->get(route('facturas.pdf', $this->orden('terminada')))->assertNotFound();
    }

    public function test_pdf_se_genera_para_una_orden_liquidada(): void
    {
        $orden = $this->liquidada();

        $this->actingAs($this->admin)->get(route('facturas.pdf', $orden))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_la_factura_muestra_lo_congelado_y_nunca_el_metodo_de_pago(): void
    {
        $orden = $this->liquidada('vip-flota'); // 70.000 − 10 % = 63.000 + 30.000 = 93.000
        $this->actingAs($this->admin)->patchJson(route('ordenes.pagar', $orden), ['metodo_pago' => 'daviplata'])->assertOk();

        // Cambiar el IVA después no altera la factura emitida.
        \App\Models\ConfiguracionTaller::withoutGlobalScopes()->where('taller_id', $this->taller->id)->update(['tasa_iva' => 5]);

        $orden = OrdenServicio::withoutGlobalScopes()->find($orden->id)->load(['detalles.repuesto', 'cliente', 'moto', 'taller']);
        $html = view('facturas.pdf', [
            'orden' => $orden,
            'totales' => app(FacturaService::class)->totalesCongelados($orden),
            'configuracion' => TarjetaOrden::configuracionDe($this->taller->id),
            'logo' => null,
        ])->render();

        $this->assertStringContainsString('N.º 1', $html);
        $this->assertStringContainsString('Se cambió el filtro y se ajustó la cadena.', $html);
        $this->assertStringContainsString('$93.000', $html);
        $this->assertStringContainsString('IVA (19 %) incluido', $html);
        $this->assertStringContainsString('Descuento cliente VIP / Flota', $html);
        $this->assertStringNotContainsStringIgnoringCase('daviplata', $html);
        $this->assertStringNotContainsStringIgnoringCase('metodo', $html);
        $this->assertStringNotContainsStringIgnoringCase('método', $html);
    }
}
