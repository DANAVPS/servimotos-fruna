<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; color: #1f2937; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; }
        th { background-color: #f3f4f6; }
        .text-right { text-align: right; }
        .totales { margin-top: 15px; width: 300px; margin-left: auto; }
        .totales td { border: none; padding: 3px 8px; }
        .total-final { font-weight: bold; font-size: 14px; border-top: 2px solid #1f2937; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $orden->taller->nombre }}</h1>
        <p>NIT: {{ $orden->taller->nit }}</p>
        <p>Factura de Servicio — Orden #{{ $orden->id }}</p>
        <p>Fecha: {{ now()->format('d/m/Y') }}</p>
    </div>

    <table style="border: none; margin-bottom: 10px;">
        <tr style="border: none;">
            <td style="border: none;"><strong>Cliente:</strong> {{ $orden->cliente->nombre }}</td>
            <td style="border: none;"><strong>Teléfono:</strong> {{ $orden->cliente->telefono }}</td>
        </tr>
        <tr style="border: none;">
            <td style="border: none;"><strong>Moto:</strong> {{ $orden->moto->marca }} {{ $orden->moto->modelo }}</td>
            <td style="border: none;"><strong>Placa:</strong> {{ $orden->moto->placa }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Repuesto</th>
                <th class="text-right">Cant.</th>
                <th class="text-right">Precio unit.</th>
                <th class="text-right">Mano de obra</th>
                <th class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orden->detalles as $detalle)
                <tr>
                    <td>{{ $detalle->repuesto->nombre }}</td>
                    <td class="text-right">{{ $detalle->cantidad }}</td>
                    <td class="text-right">${{ number_format($detalle->precio_unitario, 0, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($detalle->mano_obra, 0, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($detalle->subtotal + $detalle->mano_obra, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totales">
        <tr><td>Subtotal:</td><td class="text-right">${{ number_format($totales['subtotal'], 0, ',', '.') }}</td></tr>
        <tr><td>IVA (19%):</td><td class="text-right">${{ number_format($totales['iva'], 0, ',', '.') }}</td></tr>
        <tr class="total-final"><td>TOTAL:</td><td class="text-right">${{ number_format($totales['total'], 0, ',', '.') }}</td></tr>
    </table>

    <p style="margin-top: 30px; font-size: 10px; color: #6b7280;">
        Tratamiento de datos personales conforme a la Ley 1581 de 2012 (Habeas Data).
    </p>
</body>
</html>
