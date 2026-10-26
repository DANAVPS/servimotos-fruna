{{--
    Factura del cliente. Regla 3: aquí NUNCA aparece el método de pago.
    Todos los valores son los totales congelados al liquidar ($totales), no se recalculan.
--}}
@use('App\Support\Formato')
@php
    $hayDescuento = $orden->detalles->contains(fn ($d) => (float) $d->descuento_aplicado_pct > 0);
    $tasa = rtrim(rtrim(number_format($totales['tasa_iva'], 2, ',', '.'), '0'), ',');
    $tipoCliente = $orden->cliente->tipoClienteEfectivo();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Factura {{ $orden->numero_factura }}</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 11px; color: #1f2937; }
        table { width: 100%; border-collapse: collapse; }
        .encabezado td { vertical-align: top; }
        .logo { max-height: 60px; max-width: 160px; margin-bottom: 6px; }
        .taller h1 { margin: 0 0 4px; font-size: 18px; }
        .taller p, .documento p { margin: 1px 0; }
        .documento { text-align: right; }
        .documento .numero { font-size: 16px; font-weight: bold; }
        .caja { border: 1px solid #d1d5db; border-radius: 4px; padding: 8px 10px; margin-top: 14px; }
        .caja h2 { margin: 0 0 6px; font-size: 11px; text-transform: uppercase; color: #6b7280; letter-spacing: .5px; }
        .datos td { padding: 2px 0; width: 50%; }
        .items { margin-top: 14px; }
        .items th, .items td { border: 1px solid #d1d5db; padding: 5px 7px; text-align: left; }
        .items th { background-color: #f3f4f6; font-size: 10px; text-transform: uppercase; }
        .derecha { text-align: right !important; }
        .descuento { color: #047857; }
        .seccion { margin-top: 14px; font-size: 11px; text-transform: uppercase; color: #6b7280; letter-spacing: .5px; }
        .observaciones { margin-top: 4px; padding: 8px 10px; background: #f9fafb; border-left: 3px solid #6366f1; white-space: pre-line; }
        .totales { margin-top: 14px; width: 300px; margin-left: auto; }
        .totales td { padding: 3px 8px; }
        .total-final td { font-weight: bold; font-size: 14px; border-top: 2px solid #1f2937; padding-top: 6px; }
        .nota { font-size: 9px; color: #6b7280; }
        .pie { margin-top: 30px; font-size: 9px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 6px; }
    </style>
</head>
<body>
    <table class="encabezado">
        <tr>
            <td class="taller">
                @if($logo)
                    <img src="{{ $logo }}" class="logo" alt="">
                @endif
                <h1>{{ $orden->taller->nombre }}</h1>
                <p>NIT {{ $orden->taller->nit }}</p>
                @if($configuracion->direccion) <p>{{ $configuracion->direccion }}</p> @endif
                @if($configuracion->telefono_contacto) <p>Tel. {{ $configuracion->telefono_contacto }}</p> @endif
                @if($configuracion->email_contacto) <p>{{ $configuracion->email_contacto }}</p> @endif
            </td>
            <td class="documento">
                <p>FACTURA DE SERVICIO</p>
                <p class="numero">N.º {{ $orden->numero_factura }}</p>
                <p>Fecha: {{ $orden->liquidada_at?->timezone('America/Bogota')->format('d/m/Y h:i a') }}</p>
                <p>Orden de servicio #{{ $orden->id }}</p>
            </td>
        </tr>
    </table>

    <div class="caja">
        <table class="datos">
            <tr>
                <td><strong>Cliente:</strong> {{ $orden->cliente->nombre }}</td>
                <td><strong>Placa:</strong> {{ $orden->moto->placa }}</td>
            </tr>
            <tr>
                <td><strong>Cédula / NIT:</strong> {{ $orden->cliente->cedula_nit ?? '—' }}</td>
                <td><strong>Moto:</strong> {{ $orden->moto->marca }} {{ $orden->moto->modelo }}{{ $orden->moto->anio ? " ({$orden->moto->anio})" : '' }}</td>
            </tr>
            <tr>
                <td><strong>Teléfono:</strong> {{ $orden->cliente->telefono }}</td>
                <td>
                    @if($hayDescuento)
                        <strong>Cliente {{ $tipoCliente->nombre }}:</strong> descuento en repuestos
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <p class="seccion">Repuestos</p>
    @if($orden->detalles->isEmpty())
        <p class="nota">Este servicio no incluyó repuestos.</p>
    @else
        <table class="items">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Descripción</th>
                    <th class="derecha">Cant.</th>
                    @if($hayDescuento)
                        <th class="derecha">Precio lista</th>
                        <th class="derecha">Desc.</th>
                    @endif
                    <th class="derecha">Precio unit.</th>
                    <th class="derecha">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($orden->detalles->sortBy('id') as $detalle)
                    <tr>
                        <td>{{ $detalle->repuesto?->codigo_sku ?? '—' }}</td>
                        <td>{{ $detalle->repuesto?->nombre ?? 'Repuesto' }}</td>
                        <td class="derecha">{{ $detalle->cantidad }}</td>
                        @if($hayDescuento)
                            <td class="derecha">{{ Formato::pesos($detalle->precio_unitario_original) }}</td>
                            <td class="derecha descuento">
                                {{ (float) $detalle->descuento_aplicado_pct > 0 ? rtrim(rtrim(number_format($detalle->descuento_aplicado_pct, 2, ',', '.'), '0'), ',') . ' %' : '—' }}
                            </td>
                        @endif
                        <td class="derecha">{{ Formato::pesos($detalle->precio_unitario) }}</td>
                        <td class="derecha">{{ Formato::pesos($detalle->subtotal) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="seccion">Mano de obra</p>
    <table class="items" style="margin-top: 4px;">
        <tr>
            <td>Mano de obra del servicio</td>
            <td class="derecha" style="width: 120px;">{{ Formato::pesos($totales['mano_obra']) }}</td>
        </tr>
    </table>

    <p class="seccion">Observaciones técnicas</p>
    <div class="observaciones">{{ $orden->observaciones_tecnicas }}</div>

    <table class="totales">
        @if($totales['descuento_total'] > 0)
            <tr><td>Repuestos (precio lista)</td><td class="derecha">{{ Formato::pesos($totales['subtotal_repuestos'] + $totales['descuento_total']) }}</td></tr>
            <tr class="descuento"><td>Descuento cliente {{ $tipoCliente->nombre }}</td><td class="derecha">−{{ Formato::pesos($totales['descuento_total']) }}</td></tr>
        @endif
        <tr><td>Repuestos</td><td class="derecha">{{ Formato::pesos($totales['subtotal_repuestos']) }}</td></tr>
        <tr><td>Mano de obra</td><td class="derecha">{{ Formato::pesos($totales['mano_obra']) }}</td></tr>
        @if($configuracion->discriminar_iva_en_factura)
            <tr><td>Base gravable</td><td class="derecha">{{ Formato::pesos($totales['subtotal']) }}</td></tr>
            <tr><td>IVA ({{ $tasa }} %){{ $totales['precios_incluian_iva'] ? ' incluido' : '' }}</td><td class="derecha">{{ Formato::pesos($totales['iva']) }}</td></tr>
        @endif
        <tr class="total-final"><td>TOTAL</td><td class="derecha">{{ Formato::pesos($totales['total']) }}</td></tr>
    </table>
    @if(! $configuracion->discriminar_iva_en_factura && $totales['iva'] > 0)
        <p class="nota" style="text-align: right;">Valores con IVA ({{ $tasa }} %) incluido.</p>
    @endif

    <p class="pie">
        Gracias por confiar en {{ $orden->taller->nombre }}.
        Sus datos personales se tratan conforme a la Ley 1581 de 2012 (Habeas Data) y solo se usan para la prestación del servicio.
    </p>
</body>
</html>
