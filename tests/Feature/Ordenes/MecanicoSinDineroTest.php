<?php

namespace Tests\Feature\Ordenes;

use App\Models\Cliente;
use App\Models\Moto;
use App\Models\OrdenServicio;
use App\Models\Repuesto;
use App\Models\Taller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\UsaTaller;
use Tests\TestCase;

/**
 * El mecánico no ve dinero (precios, totales, IVA, método de pago) ni datos
 * de contacto del cliente en ninguna pantalla de órdenes: ni en el HTML
 * pintado ni en el JSON que la página lleva incrustado.
 */
class MecanicoSinDineroTest extends TestCase
{
    use RefreshDatabase, UsaTaller;

    /** Factura de 71.234 + 28.766 = 100.000 con IVA incluido (base 84.034, IVA 15.966). */
    private const SENSIBLES = [
        'Gómez', 'Rojas', '1020304050', '3001234567',
        '100.000', '100000', '71.234', '71234', '28.766', '28766', '84.034', '84034', '15.966', '15966',
        'Precio', 'Subtotal', 'Base gravable', 'Ver factura PDF',
        'Marcar como pagada', 'Corregir factura', 'metodo_pago', 'Método',
        'Efectivo', 'Transferencia', 'Nequi', 'Daviplata',
    ];

    private Taller $taller;

    private User $admin;

    private User $mecanico;

    private OrdenServicio $orden;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('facturas');

        $this->taller = Taller::factory()->create();
        $this->admin = User::factory()->administradora()->conPin()->deTaller($this->taller)->create();
        $this->mecanico = User::factory()->mecanico()->deTaller($this->taller)->create();
        $repuesto = Repuesto::factory()->create([
            'taller_id' => $this->taller->id,
            'nombre' => 'Kit de arrastre',
            'precio_costo' => 50000,
            'precio_venta' => 71234,
            'stock_actual' => 5,
        ]);

        $this->enTaller($this->taller);
        $cliente = Cliente::factory()->create([
            'taller_id' => $this->taller->id,
            'nombre' => 'Andrés Gómez Rojas',
            'cedula_nit' => '1020304050',
            'telefono' => '3001234567',
        ]);
        $moto = Moto::factory()->create(['cliente_id' => $cliente->id, 'placa' => 'ABC12D']);
        $this->orden = OrdenServicio::create(['cliente_id' => $cliente->id, 'moto_id' => $moto->id, 'estado' => 'terminada']);
        $this->enTaller(null);

        $this->actingAs($this->admin)->postJson(route('facturas.generar', $this->orden), [
            'items' => [['repuesto_id' => $repuesto->id, 'cantidad' => 1]],
            'mano_obra' => 28766,
            'observaciones_tecnicas' => 'Se cambió el kit de arrastre.',
        ])->assertOk()->assertJsonPath('orden.total', 100000);
    }

    private function pagar(): void
    {
        $this->actingAs($this->admin)->patchJson(route('ordenes.pagar', $this->orden), ['metodo_pago' => 'nequi'])->assertOk();
    }

    private function assertSinDatosSensibles($respuesta): void
    {
        foreach (self::SENSIBLES as $sensible) {
            $respuesta->assertDontSee($sensible, false);
        }
    }

    public function test_kanban_del_mecanico_sin_dinero_ni_contacto(): void
    {
        $respuesta = $this->actingAs($this->mecanico)->get(route('ordenes.kanban'))->assertOk();

        $this->assertSinDatosSensibles($respuesta);
        $respuesta->assertViewHas('tarjetas', fn ($tarjetas) => $tarjetas->sole()['total'] === null
            && $tarjetas->sole()['mano_obra'] === null
            && $tarjetas->sole()['url_pdf'] === null
            && $tarjetas->sole()['cliente'] === 'Andrés'
            && $tarjetas->sole()['numero_factura'] === 1);
    }

    public function test_kanban_de_una_orden_pagada_sin_metodo_de_pago(): void
    {
        $this->pagar();

        $this->assertSinDatosSensibles($this->actingAs($this->mecanico)->get(route('ordenes.kanban'))->assertOk());
    }

    public function test_detalle_listo_para_reclamar_sin_dinero_ni_contacto(): void
    {
        $respuesta = $this->actingAs($this->mecanico)->get(route('ordenes.show', $this->orden))
            ->assertOk()
            ->assertSee('Andrés')
            ->assertSee('Factura N.º 1')
            ->assertSee('Kit de arrastre')
            ->assertSee('Se cambió el kit de arrastre.');

        $this->assertSinDatosSensibles($respuesta);
    }

    public function test_detalle_pagada_sin_metodo_de_pago(): void
    {
        $this->pagar();

        $respuesta = $this->actingAs($this->mecanico)->get(route('ordenes.show', $this->orden))
            ->assertOk()
            ->assertSee('Orden ya pagada.');

        $this->assertSinDatosSensibles($respuesta);
    }

    public function test_el_mecanico_no_puede_abrir_el_pdf_de_la_factura(): void
    {
        $this->actingAs($this->mecanico)->get(route('facturas.pdf', $this->orden))->assertForbidden();
    }

    public function test_las_respuestas_json_al_mecanico_tampoco_traen_dinero(): void
    {
        $this->enTaller($this->taller);
        $otra = OrdenServicio::create([
            'cliente_id' => $this->orden->cliente_id,
            'moto_id' => $this->orden->moto_id,
            'estado' => 'reparacion',
        ]);
        $this->enTaller(null);

        $this->actingAs($this->mecanico)->patchJson(route('ordenes.estado', $otra), ['estado' => 'terminada'])
            ->assertOk()
            ->assertJsonPath('orden.cliente', 'Andrés')
            ->assertJsonPath('orden.total', null)
            ->assertJsonPath('orden.mano_obra', null)
            ->assertJsonPath('orden.url_pdf', null);
    }

    public function test_la_administradora_sigue_viendo_todo(): void
    {
        $this->actingAs($this->admin)->get(route('ordenes.show', $this->orden))
            ->assertOk()
            ->assertSee('Andrés Gómez Rojas')
            ->assertSee('1020304050')
            ->assertSee('$100.000')
            ->assertSee('Marcar como pagada')
            ->assertSee('Corregir factura')
            ->assertSee('Ver factura PDF');

        $this->actingAs($this->admin)->get(route('ordenes.kanban'))
            ->assertOk()
            ->assertSee('Marcar como pagada')
            ->assertViewHas('tarjetas', fn ($tarjetas) => $tarjetas->sole()['total'] === 100000.0);
    }
}
