<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Distribuidor;
use App\Models\Mecanico;
use App\Models\Moto;
use App\Models\OrdenServicio;
use App\Models\Repuesto;
use App\Models\Taller;
use App\Models\TipoCliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\UsaTaller;
use Tests\TestCase;

/**
 * Bloque 4b: dashboard ejecutivo y su endpoint de consulta periódica.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase, UsaTaller;

    private Taller $taller;

    private User $admin;

    private Mecanico $carlos;

    private Mecanico $pedro;

    private int $placas = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // Jueves 15/10/2026 a mediodía en Colombia: la semana empieza el lunes 12 y el mes el 1.
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00', 'America/Bogota'));

        $this->taller = Taller::factory()->create();
        $this->admin = User::factory()->administradora()->deTaller($this->taller)->create();

        $this->enTaller($this->taller);
        $this->carlos = Mecanico::create(['nombre' => 'Carlos']);
        $this->pedro = Mecanico::create(['nombre' => 'Pedro', 'tipo' => 'ayudante']);
        $this->enTaller(null);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function orden(array $atributos, string $tipo = 'regular', ?Taller $taller = null): OrdenServicio
    {
        $taller ??= $this->taller;
        $this->enTaller($taller);
        $cliente = Cliente::factory()->create([
            'taller_id' => $taller->id,
            'tipo_cliente_id' => TipoCliente::where('slug', $tipo)->value('id'),
        ]);
        $placa = 'ABC' . str_pad((string) $this->placas++, 2, '0', STR_PAD_LEFT);
        $moto = Moto::factory()->create(['cliente_id' => $cliente->id, 'placa' => $placa]);
        $orden = OrdenServicio::create(['cliente_id' => $cliente->id, 'moto_id' => $moto->id, 'fecha_hora_ingreso' => now(), ...$atributos]);
        $this->enTaller(null);

        return $orden;
    }

    /** Orden pagada en la fecha dada (hora de Colombia). */
    private function pagada(string $cuando, float $total, ?string $metodo, ?Mecanico $mecanico = null, float $manoObra = 0, float $repuestos = 0, float $descuento = 0, string $tipo = 'regular', ?Taller $taller = null): OrdenServicio
    {
        // Se guarda en UTC, como lo hace la app con now().
        $fecha = Carbon::parse($cuando, 'America/Bogota')->utc();

        return $this->orden([
            'estado' => 'pagada',
            'pagada_at' => $fecha,
            'liquidada_at' => $fecha,
            'total' => $total,
            'base_gravable' => round($total / 1.19),
            'iva' => $total - round($total / 1.19),
            'metodo_pago' => $metodo,
            'mecanico_id' => $mecanico?->id,
            'mano_obra' => $manoObra,
            'subtotal_repuestos' => $repuestos,
            'descuento_total' => $descuento,
            'observaciones_tecnicas' => "Trabajo de {$total}",
        ], $tipo, $taller);
    }

    private function datos(string $periodo = 'semanal'): array
    {
        return $this->actingAs($this->admin)->getJson(route('dashboard.datos', ['periodo' => $periodo]))->assertOk()->json();
    }

    // --- Acceso ---

    public function test_el_mecanico_va_a_mi_dia_y_no_consulta_el_endpoint(): void
    {
        $mecanico = User::factory()->mecanico()->deTaller($this->taller)->create();

        $this->actingAs($mecanico)->get(route('dashboard'))->assertRedirect(route('mi-dia'));
        $this->actingAs($mecanico)->getJson(route('dashboard.datos'))->assertForbidden();
    }

    public function test_la_administradora_ve_el_dashboard(): void
    {
        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Caja por método de pago')
            ->assertSee('+ Crear orden manual')
            ->assertSee('Últimas órdenes pagadas')
            ->assertSee('dashboardEjecutivo(', false);
    }

    public function test_periodo_invalido_se_rechaza(): void
    {
        $this->actingAs($this->admin)->getJson(route('dashboard.datos', ['periodo' => 'anual']))
            ->assertJsonValidationErrors('periodo');
    }

    // --- Caja y periodos ---

    public function test_caja_semanal_y_mensual(): void
    {
        $this->pagada('2026-10-14 10:00', 119000, 'efectivo');   // esta semana
        $this->pagada('2026-10-12 00:30', 59500, 'nequi');       // lunes, esta semana
        $this->pagada('2026-10-11 23:30', 23800, 'efectivo');    // domingo: solo el mes
        $this->pagada('2026-09-30 18:00', 11900, 'efectivo');    // mes anterior: nada

        $semana = $this->datos('semanal');
        $this->assertSame(['ordenes' => 2, 'total' => 178500, 'sin_iva' => 150000, 'iva' => 28500], $semana['caja']);
        $this->assertSame('12/10/2026', $semana['periodo']['desde']);

        $mes = $this->datos('mensual');
        $this->assertSame(3, $mes['caja']['ordenes']);
        $this->assertEquals(202300, $mes['caja']['total']);
        $this->assertSame('01/10/2026', $mes['periodo']['desde']);
    }

    public function test_desglose_por_metodo_de_pago_con_sin_registrar(): void
    {
        $this->pagada('2026-10-13 10:00', 100000, 'efectivo');
        $this->pagada('2026-10-13 11:00', 50000, 'efectivo');
        $this->pagada('2026-10-14 10:00', 30000, 'daviplata');
        $this->pagada('2026-10-14 11:00', 20000, null);

        $metodos = collect($this->datos()['metodos_pago'])->keyBy('clave');

        $this->assertSame(['efectivo', 'transferencia', 'nequi', 'daviplata', 'sin_registrar'], $metodos->keys()->all());
        $this->assertEquals(150000, $metodos['efectivo']['total']);
        $this->assertSame(2, $metodos['efectivo']['ordenes']);
        $this->assertEquals(0, $metodos['transferencia']['total']);
        $this->assertEquals(30000, $metodos['daviplata']['total']);
        $this->assertEquals(20000, $metodos['sin_registrar']['total']);
    }

    public function test_sin_registrar_no_aparece_si_no_hay_pagos_asi(): void
    {
        $this->pagada('2026-10-13 10:00', 100000, 'efectivo');

        $this->assertNotContains('sin_registrar', array_column($this->datos()['metodos_pago'], 'clave'));
    }

    public function test_ingresos_descuentos_y_mecanicos(): void
    {
        $this->pagada('2026-10-13 10:00', 100000, 'efectivo', $this->carlos, manoObra: 40000, repuestos: 60000);
        $this->pagada('2026-10-14 10:00', 90000, 'nequi', $this->carlos, manoObra: 20000, repuestos: 70000, descuento: 7000, tipo: 'vip-flota');
        $this->pagada('2026-10-14 12:00', 80000, 'nequi', $this->pedro, manoObra: 50000, repuestos: 30000, descuento: 3000, tipo: 'vip-flota');
        $this->pagada('2026-10-14 13:00', 10000, 'efectivo', null, manoObra: 10000);

        $datos = $this->datos();

        $this->assertEquals(['mano_obra' => 120000, 'repuestos' => 160000], $datos['ingresos']);
        $this->assertSame([['tipo' => 'VIP / Flota', 'ordenes' => 2, 'descuento' => 10000]], array_map(fn ($f) => [...$f, 'descuento' => (int) $f['descuento']], $datos['descuentos']));

        $mecanicos = $datos['mecanicos'];
        $this->assertSame(['Carlos', 'Pedro', 'Sin asignar'], array_column($mecanicos, 'nombre'));
        $this->assertEquals(60000, $mecanicos[0]['mano_obra']);
        $this->assertSame(2, $mecanicos[0]['ordenes']);
        $this->assertSame('ayudante', $mecanicos[1]['tipo']);
    }

    // --- Estado actual (sin periodo) ---

    public function test_cuentas_por_cobrar_y_motos_en_riesgo(): void
    {
        $this->orden(['estado' => 'listo_para_reclamar', 'total' => 50000, 'liquidada_at' => now()->subDays(4)]);
        $this->orden(['estado' => 'listo_para_reclamar', 'total' => 25000, 'liquidada_at' => now()->subDay()]);
        $this->orden(['estado' => 'reparacion', 'fecha_hora_ingreso' => now()->subDays(9), 'mecanico_id' => $this->carlos->id]);
        $this->orden(['estado' => 'en_revision', 'fecha_hora_ingreso' => now()->subDays(3)]);
        $this->orden(['estado' => 'en_revision', 'fecha_hora_ingreso' => now()]);

        $datos = $this->datos();

        $this->assertEquals(['ordenes' => 2, 'total' => 75000, 'dias_mas_antigua' => 4], $datos['por_cobrar']);
        $this->assertSame(1, $datos['riesgo']['rojas']);
        $this->assertSame(1, $datos['riesgo']['amarillas']);
        $this->assertSame(9, $datos['riesgo']['lista'][0]['dias']);
        $this->assertSame('Carlos', $datos['riesgo']['lista'][0]['mecanico']);
    }

    public function test_bitacora_de_las_ultimas_cinco_pagadas(): void
    {
        foreach (range(1, 6) as $dia) {
            $this->pagada("2026-10-0{$dia} 10:00", $dia * 1000, 'efectivo');
        }

        $bitacora = $this->datos()['bitacora'];

        $this->assertCount(5, $bitacora);
        $this->assertSame('Trabajo de 6000', $bitacora[0]['observaciones']);
        $this->assertSame('Efectivo', $bitacora[0]['metodo_pago']);
        $this->assertSame('Trabajo de 2000', $bitacora[4]['observaciones']);
    }

    public function test_stock_critico_solo_bajo_el_minimo_y_ordenado(): void
    {
        $this->enTaller($this->taller);
        $distribuidor = Distribuidor::factory()->create(['taller_id' => $this->taller->id, 'nombre' => 'Motopartes Bogotá']);
        $crear = function (string $nombre, string $sku, int $stock, int $minimo) use ($distribuidor) {
            $repuesto = Repuesto::factory()->create([
                'taller_id' => $this->taller->id, 'nombre' => $nombre, 'codigo_sku' => $sku,
                'stock_actual' => $stock, 'stock_minimo' => $minimo,
            ]);
            $repuesto->distribuidores()->attach($distribuidor->id, ['taller_id' => $this->taller->id]);
        };
        $crear('Filtro de aire', 'FIL-AIR-01', 1, 4);   // 25 %
        $crear('Filtro de aire', 'FIL-AIR-02', 0, 2);   // agotado
        $crear('Batería Kontrol', 'BAT-KON-01', 3, 4);  // 75 %
        $crear('Bujía', 'BUJ-01', 0, 5);                // agotado, faltan más
        $crear('Cadena', 'CAD-01', 2, 2);               // en el límite: no es crítico
        $crear('Sobrado', 'SOB-01', 50, 2);
        $this->enTaller(null);

        $grupo = $this->datos()['stock'][0];

        $this->assertSame('Motopartes Bogotá', $grupo['distribuidor']);
        $this->assertSame(route('distribuidores.solicitud', $distribuidor), $grupo['url_solicitud']);
        $this->assertSame(2, $grupo['agotados']);
        $this->assertSame(['BUJ-01', 'FIL-AIR-02', 'FIL-AIR-01', 'BAT-KON-01'], array_column($grupo['repuestos'], 'codigo'));
        $this->assertSame(['stock' => 1, 'minimo' => 4], array_intersect_key($grupo['repuestos'][2], ['stock' => 0, 'minimo' => 0]));
    }

    public function test_sin_repuestos_bajo_el_minimo_no_hay_grupos(): void
    {
        $this->enTaller($this->taller);
        Repuesto::factory()->create(['taller_id' => $this->taller->id, 'stock_actual' => 2, 'stock_minimo' => 2]);
        $this->enTaller(null);

        $this->assertSame([], $this->datos()['stock']);
    }

    public function test_no_mezcla_datos_de_otro_taller(): void
    {
        $otro = Taller::factory()->create();
        $this->pagada('2026-10-14 10:00', 999000, 'efectivo', taller: $otro);
        $this->orden(['estado' => 'listo_para_reclamar', 'total' => 888000], taller: $otro);
        $this->pagada('2026-10-14 11:00', 10000, 'efectivo');

        $datos = $this->datos();

        $this->assertEquals(10000, $datos['caja']['total']);
        $this->assertSame(0, $datos['por_cobrar']['ordenes']);
        $this->assertCount(1, $datos['bitacora']);
    }
}
