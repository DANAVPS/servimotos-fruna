<?php

namespace Tests\Feature\Inventario;

use App\Models\Distribuidor;
use App\Models\Repuesto;
use App\Models\Taller;
use App\Services\DistribuidorService;
use App\Services\InventarioService;
use App\Services\SolicitudPedidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsaTaller;
use Tests\TestCase;

class SolicitudPedidoTest extends TestCase
{
    use RefreshDatabase, UsaTaller;

    private Taller $taller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->taller = Taller::factory()->create(['nombre' => 'Servimotos Fruna']);
        $this->enTaller($this->taller);
    }

    private function repuesto(array $datos = []): Repuesto
    {
        return Repuesto::factory()->create(['taller_id' => $this->taller->id, ...$datos]);
    }

    private function distribuidor(array $datos = []): Distribuidor
    {
        return Distribuidor::factory()->create(['taller_id' => $this->taller->id, ...$datos]);
    }

    private function vincular(Repuesto $repuesto, Distribuidor $distribuidor, array $pivot = []): void
    {
        $distribuidor->repuestos()->attach($repuesto->id, ['taller_id' => $this->taller->id, ...$pivot]);
    }

    public function test_cantidad_sugerida_repone_hasta_el_doble_del_minimo(): void
    {
        $this->assertSame(5, $this->repuesto(['stock_actual' => 1, 'stock_minimo' => 3])->cantidadSugeridaPedido());
        $this->assertSame(6, $this->repuesto(['stock_actual' => 0, 'stock_minimo' => 3])->cantidadSugeridaPedido());
        $this->assertSame(1, $this->repuesto(['stock_actual' => 0, 'stock_minimo' => 0])->cantidadSugeridaPedido());
    }

    public function test_alertas_se_agrupan_por_el_distribuidor_que_entrega_mas_rapido(): void
    {
        $rapido = $this->distribuidor(['nombre' => 'Rápido']);
        $lento = $this->distribuidor(['nombre' => 'Lento']);
        $inactivo = $this->distribuidor(['nombre' => 'Inactivo', 'activo' => false]);

        $filtro = $this->repuesto(['nombre' => 'Filtro', 'stock_actual' => 1, 'stock_minimo' => 3]);
        $this->vincular($filtro, $lento, ['tiempo_entrega_dias' => 5]);
        $this->vincular($filtro, $rapido, ['tiempo_entrega_dias' => 2]);
        $this->vincular($filtro, $inactivo, ['tiempo_entrega_dias' => 1]);

        $kit = $this->repuesto(['nombre' => 'Kit', 'stock_actual' => 0, 'stock_minimo' => 1]);
        $this->vincular($kit, $lento, ['tiempo_entrega_dias' => 5]);

        $this->repuesto(['nombre' => 'Bujía', 'stock_actual' => 0, 'stock_minimo' => 2]);
        $this->repuesto(['nombre' => 'Con stock', 'stock_actual' => 10, 'stock_minimo' => 2]);

        $grupos = app(InventarioService::class)->alertasAgrupadasPorDistribuidor();

        $this->assertSame(['Lento', 'Rápido', null], $grupos->map(fn ($g) => $g['distribuidor']?->nombre)->all());
        $this->assertSame(['Kit'], $grupos[0]['repuestos']->pluck('nombre')->all());
        $this->assertSame(['Filtro'], $grupos[1]['repuestos']->pluck('nombre')->all());
        $this->assertSame(['Bujía'], $grupos[2]['repuestos']->pluck('nombre')->all());

        $this->assertSame(['Filtro'], app(DistribuidorService::class)->repuestosBajos($rapido)->pluck('nombre')->all());
        $this->assertSame(['Kit'], app(DistribuidorService::class)->repuestosBajos($lento)->pluck('nombre')->all());
    }

    public function test_el_pedido_toma_codigos_y_costos_de_la_base_e_ignora_repuestos_ajenos(): void
    {
        $distribuidor = $this->distribuidor();
        $filtro = $this->repuesto(['nombre' => 'Filtro', 'precio_costo' => 7000, 'referencia_proveedor' => 'REF-FILTRO']);
        $kit = $this->repuesto(['nombre' => 'Kit', 'precio_costo' => 50000]);
        $noVinculado = $this->repuesto(['nombre' => 'No vinculado']);

        $this->vincular($filtro, $distribuidor);
        $this->vincular($kit, $distribuidor, ['codigo_proveedor' => 'KIT-99', 'precio_costo_proveedor' => 45000]);

        $pedido = app(SolicitudPedidoService::class)->armar($distribuidor, [
            $filtro->id => '4',
            $kit->id => 2,
            $noVinculado->id => 10,
            999999 => 3,
        ]);

        $this->assertSame(['Filtro', 'Kit'], array_column($pedido['lineas'], 'nombre'));
        $this->assertSame(['REF-FILTRO', 'KIT-99'], array_column($pedido['lineas'], 'codigo_proveedor'));
        $this->assertEquals([28000, 90000], array_column($pedido['lineas'], 'subtotal'));
        $this->assertSame(6, $pedido['total_unidades']);
        $this->assertEquals(118000, $pedido['total_estimado']);
    }

    public function test_codigo_para_el_proveedor_nunca_usa_el_sku_interno(): void
    {
        $distribuidor = $this->distribuidor();
        $conCodigoPropio = $this->repuesto(['nombre' => 'A', 'codigo_sku' => 'REP-A', 'referencia_proveedor' => 'REF-A']);
        $soloReferencia = $this->repuesto(['nombre' => 'B', 'codigo_sku' => 'REP-B', 'referencia_proveedor' => 'REF-B']);
        $sinCodigo = $this->repuesto(['nombre' => 'C', 'codigo_sku' => 'REP-C']);

        $this->vincular($conCodigoPropio, $distribuidor, ['codigo_proveedor' => 'DIST-A']);
        $this->vincular($soloReferencia, $distribuidor, ['codigo_proveedor' => '  ']);
        $this->vincular($sinCodigo, $distribuidor);

        $servicio = app(SolicitudPedidoService::class);
        $pedido = $servicio->armar($distribuidor, [$conCodigoPropio->id => 1, $soloReferencia->id => 1, $sinCodigo->id => 1]);

        $this->assertSame(['DIST-A', 'REF-B', null], array_column($pedido['lineas'], 'codigo_proveedor'));

        $texto = $servicio->textoWhatsApp($pedido);
        $this->assertStringContainsString("3. C\n   Ref: ⚠ sin código · Cant: 1", $texto);
        $this->assertStringNotContainsString('REP-', $texto);
    }

    public function test_cantidades_en_cero_se_excluyen_y_sin_cantidades_usa_las_sugeridas(): void
    {
        $distribuidor = $this->distribuidor();
        $bajo = $this->repuesto(['nombre' => 'Bajo', 'stock_actual' => 1, 'stock_minimo' => 3]);
        $otro = $this->repuesto(['nombre' => 'Otro', 'stock_actual' => 0, 'stock_minimo' => 2]);
        $this->vincular($bajo, $distribuidor);
        $this->vincular($otro, $distribuidor);

        $servicio = app(SolicitudPedidoService::class);

        $conCero = $servicio->armar($distribuidor, [$bajo->id => 0, $otro->id => 3]);
        $this->assertSame(['Otro'], array_column($conCero['lineas'], 'nombre'));

        $sugerido = $servicio->armar($distribuidor);
        $this->assertSame([5, 4], array_column($sugerido['lineas'], 'cantidad'));
    }

    public function test_texto_de_whatsapp_lista_referencias_y_no_muestra_costos(): void
    {
        $distribuidor = $this->distribuidor(['nombre' => 'Motopartes', 'contacto' => 'Carlos']);
        $filtro = $this->repuesto(['nombre' => 'Filtro de aceite K&N', 'precio_costo' => 6600, 'referencia_proveedor' => 'KN-204']);
        $kit = app(InventarioService::class)->crear([
            'nombre' => 'Kit de arrastre PXR',
            'precio_costo' => 88200,
            'precio_venta' => 147000,
            'stock_actual' => 0,
            'stock_minimo' => 1,
            'compatibilidades' => 'Duke 200',
        ]);
        $this->vincular($filtro, $distribuidor);
        $this->vincular($kit, $distribuidor);

        $servicio = app(SolicitudPedidoService::class);
        $texto = $servicio->textoWhatsApp($servicio->armar($distribuidor, [$filtro->id => 6, $kit->id => 1]));

        $this->assertStringContainsString('Hola Carlos, le saluda Servimotos Fruna.', $texto);
        $this->assertStringContainsString("1. Filtro de aceite K&N\n   Ref: KN-204 · Cant: 6", $texto);
        $this->assertStringContainsString("2. Kit de arrastre PXR\n   Ref: ⚠ sin código · Moto: Duke 200 · Cant: 1", $texto);
        $this->assertStringContainsString('Total: 2 referencias, 7 unidades.', $texto);
        $this->assertStringNotContainsString('6600', $texto);
        $this->assertStringNotContainsString('$', $texto);
    }

    public function test_enlace_de_whatsapp_agrega_indicativo_colombiano(): void
    {
        $distribuidor = $this->distribuidor(['telefono_whatsapp' => '300 123 4567']);

        $this->assertSame('https://wa.me/573001234567', $distribuidor->enlaceWhatsApp());
        $this->assertSame('https://wa.me/573001234567?text=Hola%20mundo', $distribuidor->enlaceWhatsApp('Hola mundo'));
        $this->assertNull($this->distribuidor(['telefono_whatsapp' => null])->enlaceWhatsApp());
    }
}
