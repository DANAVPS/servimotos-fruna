<?php

namespace Tests\Unit;

use App\Models\TipoCliente;
use App\Support\Identificadores;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TipoClienteTest extends TestCase
{
    public static function casosDescuento(): array
    {
        return [
            'regular 0%' => [0, 35000, 35000],
            'vip 10%' => [10, 35000, 31500],
            'redondea a pesos' => [12.5, 11000, 9625],
            'redondea a pesos (2)' => [7.25, 11999, 11129],
            '100% queda en cero' => [100, 35000, 0],
            'precio en texto (decimal de BD)' => [10, '42000.00', 37800],
        ];
    }

    #[DataProvider('casosDescuento')]
    public function test_calcula_precio_con_descuento(float $porcentaje, float|string $precioLista, float $esperado): void
    {
        $tipo = new TipoCliente(['descuento_repuestos_pct' => $porcentaje]);

        $this->assertSame((float) $esperado, $tipo->calcularPrecioConDescuento($precioLista));
    }

    public function test_un_porcentaje_fuera_de_rango_nunca_deja_precio_negativo_ni_mayor(): void
    {
        $this->assertSame(0.0, (new TipoCliente(['descuento_repuestos_pct' => 150]))->calcularPrecioConDescuento(1000));
        $this->assertSame(1000.0, (new TipoCliente(['descuento_repuestos_pct' => -20]))->calcularPrecioConDescuento(1000));
    }

    public function test_normaliza_placas_cedulas_y_telefonos(): void
    {
        $this->assertSame('ABC12D', Identificadores::placa(' abc-12d '));
        $this->assertSame('ABC12', Identificadores::placa('abc 12'));
        $this->assertSame('1020304050', Identificadores::cedulaNit('1.020.304.050'));
        $this->assertSame('900123456-1', Identificadores::cedulaNit('900.123.456 - 1'));
        $this->assertSame('+573001234567', Identificadores::telefono('300 123 4567'));
        $this->assertSame('+573001234567', Identificadores::telefono('+57 300-123-4567'));
        $this->assertSame('+573001234567', Identificadores::telefono('573001234567'));
        $this->assertNull(Identificadores::placa('  '));
    }
}
