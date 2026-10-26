@php use App\Support\Formato; @endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de compra — {{ $pedido['distribuidor']->nombre }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #1f2937; margin: 0; }
        h1 { font-size: 18px; margin: 0; }
        .muted { color: #6b7280; }
        .encabezado { width: 100%; border-bottom: 2px solid #1f2937; padding-bottom: 8px; margin-bottom: 14px; }
        .encabezado td { vertical-align: top; }
        .logo { max-height: 60px; max-width: 160px; }
        .bloque { width: 100%; margin-bottom: 14px; }
        .bloque td { vertical-align: top; width: 50%; padding-right: 12px; }
        .etiqueta { font-size: 9px; text-transform: uppercase; color: #6b7280; letter-spacing: .5px; }
        table.lineas { width: 100%; border-collapse: collapse; }
        table.lineas th { background: #f3f4f6; font-size: 9px; text-transform: uppercase; text-align: left; padding: 6px; border-bottom: 1px solid #d1d5db; }
        table.lineas td { padding: 6px; border-bottom: 1px solid #e5e7eb; }
        .derecha { text-align: right; }
        .centro { text-align: center; }
        .total td { font-weight: bold; border-top: 2px solid #1f2937; border-bottom: none; }
        .pie { margin-top: 24px; font-size: 9px; color: #6b7280; }
    </style>
</head>
<body>
    <table class="encabezado">
        <tr>
            <td>
                @if($logo)
                    <img src="{{ $logo }}" class="logo" alt="">
                @endif
                <h1>{{ $taller->nombre }}</h1>
                <div class="muted">
                    @if($taller->nit) NIT {{ $taller->nit }}<br> @endif
                    @if($configuracion?->direccion) {{ $configuracion->direccion }}<br> @endif
                    {{ $configuracion?->telefono_contacto ?? $taller->telefono_whatsapp }}
                    @if($configuracion?->email_contacto) · {{ $configuracion->email_contacto }} @endif
                </div>
            </td>
            <td class="derecha">
                <div class="etiqueta">Orden de compra</div>
                <div style="font-size: 14px; font-weight: bold;">{{ $pedido['fecha']->format('d/m/Y') }}</div>
                <div class="muted">{{ $pedido['fecha']->format('h:i a') }}</div>
            </td>
        </tr>
    </table>

    <table class="bloque">
        <tr>
            <td>
                <div class="etiqueta">Distribuidor</div>
                <strong>{{ $pedido['distribuidor']->nombre }}</strong><br>
                @if($pedido['distribuidor']->contacto) Atn. {{ $pedido['distribuidor']->contacto }}<br> @endif
                @if($pedido['distribuidor']->telefono_whatsapp) Tel. {{ $pedido['distribuidor']->telefono_whatsapp }}<br> @endif
                @if($pedido['distribuidor']->email) {{ $pedido['distribuidor']->email }} @endif
            </td>
            <td>
                <div class="etiqueta">Resumen</div>
                {{ count($pedido['lineas']) }} referencias · {{ $pedido['total_unidades'] }} unidades
            </td>
        </tr>
    </table>

    <table class="lineas">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 16%;">Código proveedor</th>
                <th>Repuesto</th>
                <th class="centro" style="width: 9%;">Cant.</th>
                <th class="derecha" style="width: 13%;">Costo unit.</th>
                <th class="derecha" style="width: 14%;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pedido['lineas'] as $indice => $linea)
                <tr>
                    <td>{{ $indice + 1 }}</td>
                    <td>
                        @if($linea['codigo_proveedor'])
                            {{ $linea['codigo_proveedor'] }}
                        @else
                            <span style="color: #b45309; font-weight: bold;">{{ \App\Services\SolicitudPedidoService::SIN_CODIGO }}</span>
                        @endif
                    </td>
                    <td>
                        {{ $linea['nombre'] }}
                        @if($linea['compatibilidad'])
                            <br><span class="muted">Para {{ $linea['compatibilidad'] }}</span>
                        @endif
                    </td>
                    <td class="centro">{{ $linea['cantidad'] }}</td>
                    <td class="derecha">{{ Formato::pesos($linea['costo_unitario']) }}</td>
                    <td class="derecha">{{ Formato::pesos($linea['subtotal']) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="3">Total estimado</td>
                <td class="centro">{{ $pedido['total_unidades'] }}</td>
                <td></td>
                <td class="derecha">{{ Formato::pesos($pedido['total_estimado']) }}</td>
            </tr>
        </tbody>
    </table>

    <p class="pie">
        Los costos son una estimación según el último precio registrado; el valor final lo confirma el distribuidor.
    </p>
</body>
</html>
