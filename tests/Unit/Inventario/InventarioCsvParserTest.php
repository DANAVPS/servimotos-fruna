<?php

namespace Tests\Unit\Inventario;

use App\Support\Inventario\InventarioCsvParser;
use RuntimeException;
use Tests\TestCase;

class InventarioCsvParserTest extends TestCase
{
    private const ENCABEZADO = 'Codigo_SKU;Referencias de Producto ;Nombre_Articulo;Categoria;Compatibilidad_Motos;'
        . 'Ubicacion_Estante;Stock_Actual;Stock_Minimo;Costo_Unitario_COP;Precio_Venta_COP;;';

    /**
     * @param array<int, string> $filas
     * @return array{filas: array<int, array<string, mixed>>, omitidas: array<int, array{linea: int, motivo: string}>}
     */
    private function parsear(array $filas, string $finDeLinea = "\n", int $stockMinimo = 1): array
    {
        return (new InventarioCsvParser($stockMinimo))->parsear(
            implode($finDeLinea, [self::ENCABEZADO, ...$filas])
        );
    }

    public function test_arrastra_nombre_y_categoria_de_celdas_combinadas(): void
    {
        $resultado = $this->parsear([
            ';;Empaques AKT;Culatin;TTR 125/ NKD /CR4;;14;;;8000;;112000',
            ';;;;APACHE 160;;2;;;22000;;44000',
            ';;;;FZ 16;;3;;;15000;;45000',
        ]);

        $this->assertCount(3, $resultado['filas']);

        foreach ($resultado['filas'] as $fila) {
            $this->assertSame('Empaque culatín AKT', $fila['nombre']);
            $this->assertSame('culatin', $fila['categoria_slug']);
        }

        $this->assertSame(['APACHE 160'], $resultado['filas'][1]['compatibilidades']);
    }

    public function test_al_cambiar_de_seccion_no_arrastra_el_articulo_anterior(): void
    {
        $resultado = $this->parsear([
            ';;Empaques AKT;Culatin;CB 110;;2;;;20000;;40000',
            ';;;Pistas (CUNAS;FZ 16 / LIBERO ;;4;;;75000;;300000',
        ]);

        $pistas = $resultado['filas'][1];

        $this->assertSame('pistas-cunas', $pistas['categoria_slug']);
        $this->assertSame('Pistas (Cunas)', $pistas['nombre']);
        $this->assertNotEmpty($pistas['avisos'], 'Un nombre tomado de la categoría debe quedar marcado para revisión.');
    }

    public function test_culata_y_tapa_bolante_sin_articulo_son_empaques(): void
    {
        $resultado = $this->parsear([
            ';;;Culata;Apache 125;;1;;;15000;;15000',
            ';;;Tapa bolante ;FZ 16 / SZR ;;6;;;6000;;36000',
            ';;VARROC;Tapa bolante ;NS 200;;21;;;7000;;147000',
            ';;;Tapa bolante ;BEST ;;9;;;6000;;54000',
        ]);

        $this->assertSame(
            ['Empaque culata', 'Empaque tapa bolante', 'Empaque tapa bolante Varroc', 'Empaque tapa bolante Varroc'],
            array_column($resultado['filas'], 'nombre')
        );
        $this->assertSame([], $resultado['filas'][0]['avisos']);
    }

    public function test_codigos_de_bujia_pasan_a_referencia_del_proveedor(): void
    {
        $resultado = $this->parsear([
            ';;PATA LARGA ;BUJIAS ;CR8E;;7;;;10000;;70000',
            ';;PATA CORTA ;;CR7HSA / Boxer;;12;;;10000;;120000',
        ]);

        [$larga, $corta] = $resultado['filas'];

        $this->assertSame('Bujías pata larga', $larga['nombre']);
        $this->assertSame('CR8E', $larga['referencia_proveedor']);
        $this->assertSame([], $larga['compatibilidades']);
        $this->assertSame('CR7HSA', $corta['referencia_proveedor']);
        $this->assertSame(['Boxer'], $corta['compatibilidades']);
    }

    public function test_correcciones_de_precio_y_fusion_de_filas_repetidas(): void
    {
        $parser = new InventarioCsvParser();
        $resultado = $parser->aplicarCorrecciones($this->parsear([
            'INS-LUB-003;;Aceite Castrol POWER 1 4T 15W-50;Lubricante;Universal Motos 4T;;5;;;42000;;210000',
            'INS-LUB-004;;Aceite Castrol POWER 1 4T 5W-40;Lubricante;Universal Motos 4T;;5;;;420000;;2100000',
            'REP-ARR-024;;Kit de Arrastre KONTROL;Trasmision;Honda CBF 150;;1;;;50000;;50000',
            'REP-ARR-025;;Kit de Arrastre KONTROL;Trasmision;Honda CBF 150;;1;;;50000;;50000',
        ]), [
            'precios' => ['INS-LUB-004' => 42000],
            'fusionar' => [['REP-ARR-024', 'REP-ARR-025']],
        ]);

        $filas = collect($resultado['filas'])->keyBy('codigo_sku');

        $this->assertCount(3, $filas);
        $this->assertEquals(42000, $filas['INS-LUB-004']['precio_venta']);
        $this->assertEquals(25200, $filas['INS-LUB-004']['precio_costo']);
        $this->assertSame(2, $filas['REP-ARR-024']['stock_actual']);
        $this->assertFalse($filas->has('REP-ARR-025'));
    }

    public function test_una_correccion_con_sku_inexistente_falla_en_voz_alta(): void
    {
        $parser = new InventarioCsvParser();

        $this->expectException(RuntimeException::class);

        $parser->aplicarCorrecciones($this->parsear([
            'REP-FIL-001;;Filtro de Aceite K&N;Filtros;Duke 200;;12;;6600;11000;;132000',
        ]), ['precios' => ['NO-EXISTE' => 1]]);
    }

    public function test_una_marca_sola_se_combina_con_el_articulo_de_la_seccion(): void
    {
        $resultado = $this->parsear([
            'REP-FIL-036;;Filtro de Aire ;Filtros;FZ 2.0 ;;2;1;21000;35000;;70000',
            'REP-FIL-037;;sUZUKI;Filtros;GSX R 150 ;;1;;36000;60000;;60000',
            'REP-FIL-038;;;Filtros;VIVA R ;;2;;26400;44000;;88000',
        ]);

        $this->assertSame('Filtro de Aire', $resultado['filas'][0]['nombre']);
        $this->assertSame('Filtro de Aire Suzuki', $resultado['filas'][1]['nombre']);
        $this->assertSame('Filtro de Aire Suzuki', $resultado['filas'][2]['nombre']);
    }

    public function test_el_articulo_manda_sobre_la_columna_categoria(): void
    {
        $resultado = $this->parsear([
            ';;Tensor (GUIAS);Cadenilla ;AKT 180 / XM;;2;;;22000;;44000',
            ';;BOBINAS (VARROC);ELECTRICO;NS 200;;1;;;100000;;100000',
            'REP-ARR-005;;Kit de Arrastre PXR;Trasmision;Yamaha XTZ 150;;1;;;103000;;103000',
            'REP-ARR-027;;Cadena TEC 428-132L;Trasmision;Universal Motos 4T;;14;;;45000;;630000',
            ';;Empaque (bajaj);cilindro ;Discover /Boxer;;6;;;5000;;30000',
        ]);

        $this->assertSame(
            ['tensor', 'bobinas', 'kit-de-arrastre', 'cadena', 'empaques-varios'],
            array_column($resultado['filas'], 'categoria_slug')
        );
        $this->assertSame('Empaque cilindro (Bajaj)', $resultado['filas'][4]['nombre']);
    }

    public function test_omite_filas_vacias_formulas_y_sin_precio(): void
    {
        $resultado = $this->parsear([
            ';;;;;;;;;;;0',
            'INS-LUB-016;;;;;;;;;;;0',
            ';;;;;;;;;=SUM(J2:J10);;',
            ';;;;;;;;;;;$33.188.000,00',
            'REP-FIL-001;;Filtro de Aceite K&N;Filtros;Duke 200;;12;;6600;11000;;132000',
        ]);

        $this->assertCount(1, $resultado['filas']);
        $this->assertCount(2, $resultado['omitidas']);
        $this->assertSame('Filtro de Aceite K&N', $resultado['filas'][0]['nombre']);
    }

    public function test_costo_vacio_se_calcula_al_60_por_ciento_y_stock_minimo_por_defecto(): void
    {
        $resultado = $this->parsear([
            'REP-ARR-004;;Kit de Arrastre PXR;Trasmision;Boxer CT 100;;1;1;;70000;;70000',
            'REP-ARR-005;;Kit de Arrastre PXR;Trasmision;Yamaha XTZ 150;;1;;;103000;;103000',
            'REP-FIL-001;;Filtro de Aceite K&N;Filtros;Duke 200;;12;;6600;11000;;132000',
        ], stockMinimo: 2);

        [$conMinimo, $sinMinimo, $conCosto] = $resultado['filas'];

        $this->assertEquals(42000, $conMinimo['precio_costo']);
        $this->assertTrue($conMinimo['costo_calculado']);
        $this->assertSame(1, $conMinimo['stock_minimo']);
        $this->assertSame(2, $sinMinimo['stock_minimo']);
        $this->assertTrue($sinMinimo['stock_minimo_por_defecto']);
        $this->assertEquals(6600, $conCosto['precio_costo']);
        $this->assertFalse($conCosto['costo_calculado']);
    }

    public function test_sku_repetido_recibe_sufijo_y_aviso(): void
    {
        $resultado = $this->parsear([
            'REP-ARR-016;;Kit de Arrastre PXR;Trasmision;Honda XR 125;;1;;;120000;;120000',
            'REP-ARR-016;;Kit de Arrastre KONTROL;Trasmision;Yamaha SZ-R 150;;1;;;65000;;65000',
        ]);

        $this->assertSame('REP-ARR-016', $resultado['filas'][0]['codigo_sku']);
        $this->assertSame('REP-ARR-016-2', $resultado['filas'][1]['codigo_sku']);
        $this->assertStringContainsString('repetido', $resultado['filas'][1]['avisos'][0]);
    }

    public function test_decodifica_csv_de_excel_para_mac(): void
    {
        // Mac Roman: 0x96 = ñ. Fin de línea CR, como lo exporta Excel para Mac.
        $resultado = $this->parsear(
            [";;Empaque ;tapa pi\x96on ;180 XM ;;1;;;10000;;10000"],
            "\r"
        );

        $this->assertSame('Empaque tapa piñon', $resultado['filas'][0]['nombre']);
    }

    public function test_compatibilidad_suelta_hereda_el_modelo_anterior(): void
    {
        $resultado = $this->parsear([
            'REP-FIL-015;;Filtro de Aire ;Filtros;Pulsar 125 / 135 ;;3;;12000;20000;;60000',
        ]);

        $this->assertSame(['Pulsar 125', 'Pulsar 135'], $resultado['filas'][0]['compatibilidades']);
    }

    public function test_relacion_pinon_corona_se_agrega_al_nombre(): void
    {
        $resultado = $this->parsear([
            'REP-ARR-030;;Kit de Arrastre KONTROL;Trasmision;Boxer ;;1;;;50000;14/42;50000',
        ]);

        $this->assertSame('Kit de Arrastre Kontrol 14/42', $resultado['filas'][0]['nombre']);
    }

    public function test_rechaza_un_archivo_sin_el_encabezado_esperado(): void
    {
        $this->expectException(RuntimeException::class);

        (new InventarioCsvParser())->parsear("a;b;c\n1;2;3");
    }

    public function test_archivo_real_con_las_correcciones_acordadas(): void
    {
        $parser = new InventarioCsvParser();
        $resultado = $parser->aplicarCorrecciones(
            $parser->parsearArchivo(database_path('seeders/data/inventario.csv')),
            require database_path('seeders/data/inventario_correcciones.php')
        );
        $filas = collect($resultado['filas']);

        $this->assertCount(264, $filas);
        $this->assertSame(95, $filas->filter(fn ($f) => $f['stock_actual'] <= $f['stock_minimo'])->count());
        $this->assertSame(2, $filas->firstWhere('codigo_sku', 'REP-ARR-024-2')['stock_actual']);
        $this->assertEquals(42000, $filas->firstWhere('codigo_sku', 'INS-LUB-004')['precio_venta']);
        $this->assertSame(0, $filas->filter(fn ($f) => $f['nombre'] === 'Culata' || $f['nombre'] === 'Tapa bolante')->count());
    }

    public function test_archivo_real_del_taller(): void
    {
        $resultado = (new InventarioCsvParser())->parsearArchivo(database_path('seeders/data/inventario.csv'));
        $filas = collect($resultado['filas']);

        $this->assertCount(265, $filas);
        $this->assertCount(3, $resultado['omitidas']);
        $this->assertTrue($filas->every(fn ($f) => $f['categoria_slug'] !== null), 'Toda fila debe quedar con categoría.');
        $this->assertTrue($filas->every(fn ($f) => $f['precio_costo'] <= $f['precio_venta'] || $f['avisos'] !== []));
        $this->assertSame(
            $filas->pluck('codigo_sku')->filter()->count(),
            $filas->pluck('codigo_sku')->filter()->unique()->count(),
            'Los SKU deben quedar únicos.'
        );

        $filtroKn = $filas->firstWhere('codigo_sku', 'REP-FIL-001');
        $this->assertSame('Filtro de Aceite K&N', $filtroKn['nombre']);
        $this->assertSame(['Pulsar NS 200', 'Duke 200'], $filtroKn['compatibilidades']);
        $this->assertSame('Estante A - Fila 2', $filtroKn['ubicacion_estante']);
    }
}
