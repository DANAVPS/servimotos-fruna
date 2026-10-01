@if($tipo)
    <span @class([
        'rounded-full px-2 py-0.5 text-xs font-medium',
        'bg-indigo-100 text-indigo-700' => $tipo->tieneDescuento(),
        'bg-gray-100 text-gray-600' => ! $tipo->tieneDescuento(),
    ])>
        {{ $tipo->nombre }}@if($tipo->tieneDescuento()) · −{{ rtrim(rtrim($tipo->descuento_repuestos_pct, '0'), '.') }}%@endif
    </span>
@endif
