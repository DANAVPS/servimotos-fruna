@use('App\Support\Formato')
@use('App\Support\TarjetaOrden')
@php
    $etiquetas = TarjetaOrden::ETIQUETAS_ESTADO;
    $eventos = [
        'creacion' => 'Orden creada',
        'cambio_estado' => 'Cambio de estado',
        'liquidacion' => 'Liquidada · factura emitida',
        'correccion' => 'Factura corregida',
        'pago' => 'Pagada',
    ];
    $colorEvento = [
        'creacion' => 'bg-gray-400',
        'cambio_estado' => 'bg-indigo-500',
        'liquidacion' => 'bg-blue-500',
        'correccion' => 'bg-amber-500',
        'pago' => 'bg-green-500',
    ];
    $colorSemaforo = ['verde' => 'bg-green-500', 'amarillo' => 'bg-yellow-400', 'rojo' => 'bg-red-500'];
    $usuario = auth()->user();
    // El mecánico no ve dinero (precios, totales, pago) ni contacto del cliente.
    $verDinero = TarjetaOrden::veDinero($usuario);
    $tipoCliente = $orden->cliente->tipoClienteEfectivo();
    $abrirLiquidacion = request()->boolean('liquidar') && $orden->estado === 'terminada' && $usuario->can('liquidar', $orden);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <button type="button" onclick="history.length > 1 ? history.back() : (location.href = @js(route('ordenes.kanban')))"
                        class="text-sm text-gray-500 hover:text-gray-800">← Volver</button>
                <h2 class="text-xl font-semibold text-gray-800">
                    Orden #{{ $orden->id }} — <span class="font-mono">{{ $orden->moto->placa }}</span>
                </h2>
                <span class="rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-800">{{ $etiquetas[$orden->estado] }}</span>
                @if($orden->correcciones_count)
                    <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs text-amber-700">
                        Corregida {{ $orden->correcciones_count }} {{ $orden->correcciones_count === 1 ? 'vez' : 'veces' }}
                    </span>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6"
         x-data="{ tarjeta: @js($tarjeta) }"
         x-init="@if($abrirLiquidacion) setTimeout(() => window.dispatchEvent(new CustomEvent('abrir-liquidacion', { detail: tarjeta })), 0) @endif"
         @orden-actualizada.window="window.location.href = $event.detail.orden.url">
        <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">

            @if(session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            @if($orden->estado === 'pagada')
                <div class="rounded-md border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
                    <strong>Orden ya pagada.</strong> Para corregir, cree una nueva orden.
                    @if($verDinero)
                        <span class="mt-1 block text-xs text-gray-500">
                            Pagada el {{ $orden->pagada_at?->timezone('America/Bogota')->format('d/m/Y h:i a') ?? '—' }}
                            · Método (interno): {{ $orden->metodoPagoEtiqueta() }}
                        </span>
                    @endif
                </div>
            @endif

            {{-- Datos principales --}}
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-gray-500">Cliente</dt>
                        @if($verDinero)
                            <dd class="font-medium text-gray-900">
                                <a href="{{ route('clientes.show', $orden->cliente) }}" class="hover:text-indigo-700">{{ $orden->cliente->nombre }}</a>
                            </dd>
                            <dd class="text-gray-600">{{ $orden->cliente->cedula_nit ?? 'Sin cédula/NIT' }} · {{ $orden->cliente->telefono }}</dd>
                            <dd class="text-xs text-gray-500">
                                {{ $tipoCliente->nombre }}@if((float) $tipoCliente->descuento_repuestos_pct > 0) · {{ rtrim(rtrim(number_format($tipoCliente->descuento_repuestos_pct, 2, ',', '.'), '0'), ',') }} % en repuestos @endif
                            </dd>
                        @else
                            <dd class="font-medium text-gray-900">{{ TarjetaOrden::primerNombre($orden->cliente->nombre) }}</dd>
                        @endif
                    </div>
                    <div>
                        <dt class="text-gray-500">Moto</dt>
                        <dd class="font-mono font-medium text-gray-900">{{ $orden->moto->placa }}</dd>
                        <dd class="text-gray-600">{{ $orden->moto->marca }} {{ $orden->moto->modelo }}{{ $orden->moto->anio ? " ({$orden->moto->anio})" : '' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Mecánico</dt>
                        <dd class="font-medium text-gray-900">{{ $orden->mecanico?->nombre ?? 'Sin asignar' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Ingreso</dt>
                        <dd class="flex items-center gap-2 text-gray-900">
                            {{ $orden->fecha_hora_ingreso->timezone('America/Bogota')->format('d/m/Y h:i a') }}
                            @if($tarjeta['semaforo'])
                                <span class="inline-flex items-center gap-1 text-xs text-gray-500">
                                    <span class="h-2.5 w-2.5 rounded-full {{ $colorSemaforo[$tarjeta['semaforo']] }}"></span>
                                    {{ $tarjeta['dias'] }} {{ $tarjeta['dias'] === 1 ? 'día' : 'días' }}
                                </span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Factura</dt>
                        <dd class="font-medium text-gray-900">{{ $orden->numero_factura ? "N.º {$orden->numero_factura}" : '—' }}</dd>
                    </div>
                    @if($verDinero)
                        <div>
                            <dt class="text-gray-500">Total</dt>
                            <dd class="font-medium text-gray-900">{{ $orden->estaFacturada() ? Formato::pesos($orden->total) : '—' }}</dd>
                        </div>
                    @endif
                    @if($orden->descripcion_falla)
                        <div class="sm:col-span-3">
                            <dt class="text-gray-500">Falla reportada</dt>
                            <dd class="whitespace-pre-line text-gray-900">{{ $orden->descripcion_falla }}</dd>
                        </div>
                    @endif
                </dl>

                {{-- Acciones según estado (el servidor vuelve a validar cada una) --}}
                <div class="mt-6 flex flex-wrap gap-3 border-t pt-4">
                    @if($orden->estado === 'terminada')
                        @can('liquidar', $orden)
                            <button type="button" @click="window.dispatchEvent(new CustomEvent('abrir-liquidacion', { detail: tarjeta }))"
                                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                                @if($usuario->can('emitirFactura', $orden))
                                    {{ $orden->numero_factura ? 'Emitir factura de nuevo' : 'Emitir factura' }}
                                @else
                                    {{ $tarjeta['trabajo_registrado'] ? 'Editar trabajo' : 'Registrar trabajo' }}
                                @endif
                            </button>
                        @endcan
                    @endif

                    @if($orden->estado === 'listo_para_reclamar')
                        @can('marcarPagada', $orden)
                            <button type="button" @click="window.dispatchEvent(new CustomEvent('abrir-pago', { detail: tarjeta }))"
                                    class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-500">
                                Marcar como pagada
                            </button>
                        @endcan
                        @can('corregirFactura', $orden)
                            <button type="button" @click="window.dispatchEvent(new CustomEvent('abrir-correccion'))"
                                    class="rounded-md border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-100">
                                Corregir factura
                            </button>
                        @endcan
                    @endif

                    @if($orden->estaFacturada() && $usuario->can('verFactura', $orden))
                        <a href="{{ route('facturas.pdf', $orden) }}" target="_blank" rel="noopener"
                           class="rounded-md border px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            Ver factura PDF
                        </a>
                    @endif
                </div>
            </div>

            {{-- Trabajo registrado, pendiente de factura (sin precios: aún no se han fijado) --}}
            @if($tarjeta['trabajo_registrado'])
                <div class="rounded-lg border border-sky-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-base font-semibold text-gray-900">Trabajo registrado</h3>
                        <span class="rounded-full bg-sky-100 px-2.5 py-0.5 text-xs text-sky-700">
                            {{ $verDinero ? 'Pendiente: fijar mano de obra y emitir la factura' : 'Pendiente de factura (la emite la administradora)' }}
                        </span>
                    </div>
                    @if($orden->detalles->isNotEmpty())
                        <ul class="mt-3 divide-y text-sm">
                            @foreach($orden->detalles->sortBy('id') as $detalle)
                                <li class="flex justify-between py-1.5">
                                    <span>{{ $detalle->repuesto?->nombre }} <span class="text-xs text-gray-500">{{ $detalle->repuesto?->codigo_sku }}</span></span>
                                    <span class="text-gray-600">× {{ $detalle->cantidad }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-3 text-sm text-gray-500">Sin repuestos.</p>
                    @endif
                    <h4 class="mt-4 text-sm font-medium text-gray-700">Observaciones técnicas</h4>
                    <p class="mt-1 whitespace-pre-line rounded-md border-l-4 border-sky-400 bg-gray-50 p-3 text-sm text-gray-800">{{ $orden->observaciones_tecnicas }}</p>
                </div>
            @endif

            {{-- Factura (totales congelados) --}}
            @if($orden->estaFacturada())
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">Factura N.º {{ $orden->numero_factura }}</h3>
                    <p class="text-xs text-gray-500">Emitida el {{ $orden->liquidada_at?->timezone('America/Bogota')->format('d/m/Y h:i a') }}</p>

                    @if($orden->detalles->isNotEmpty())
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="border-b text-left text-xs uppercase text-gray-500">
                                        <th class="py-2">Repuesto</th>
                                        <th class="py-2 text-right">Cant.</th>
                                        @if($verDinero)
                                            <th class="py-2 text-right">Precio lista</th>
                                            <th class="py-2 text-right">Desc.</th>
                                            <th class="py-2 text-right">Precio</th>
                                            <th class="py-2 text-right">Subtotal</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    @foreach($orden->detalles->sortBy('id') as $detalle)
                                        <tr>
                                            <td class="py-2">
                                                {{ $detalle->repuesto?->nombre }}
                                                <span class="block text-xs text-gray-500">{{ $detalle->repuesto?->codigo_sku }}</span>
                                            </td>
                                            <td class="py-2 text-right">{{ $detalle->cantidad }}</td>
                                            @if($verDinero)
                                            <td class="py-2 text-right text-gray-500">{{ Formato::pesos($detalle->precio_unitario_original) }}</td>
                                            <td class="py-2 text-right text-emerald-700">{{ (float) $detalle->descuento_aplicado_pct > 0 ? rtrim(rtrim(number_format($detalle->descuento_aplicado_pct, 2, ',', '.'), '0'), ',') . ' %' : '—' }}</td>
                                            <td class="py-2 text-right">{{ Formato::pesos($detalle->precio_unitario) }}</td>
                                            <td class="py-2 text-right font-medium">{{ Formato::pesos($detalle->subtotal) }}</td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="mt-4 text-sm text-gray-500">Sin repuestos.</p>
                    @endif

                    <div class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <h4 class="text-sm font-medium text-gray-700">Observaciones técnicas</h4>
                            <p class="mt-1 whitespace-pre-line rounded-md border-l-4 border-indigo-400 bg-gray-50 p-3 text-sm text-gray-800">{{ $orden->observaciones_tecnicas }}</p>
                        </div>
                        @if($verDinero)
                        <dl class="space-y-1 text-sm sm:ml-auto sm:w-64">
                            @if((float) $orden->descuento_total > 0)
                                <div class="flex justify-between text-emerald-700"><dt>Descuento repuestos</dt><dd>−{{ Formato::pesos($orden->descuento_total) }}</dd></div>
                            @endif
                            <div class="flex justify-between"><dt class="text-gray-600">Repuestos</dt><dd>{{ Formato::pesos($orden->subtotal_repuestos) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-600">Mano de obra</dt><dd>{{ Formato::pesos($orden->mano_obra) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-600">Base gravable</dt><dd>{{ Formato::pesos($orden->base_gravable) }}</dd></div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600">IVA ({{ rtrim(rtrim(number_format($orden->tasa_iva_aplicada, 2, ',', '.'), '0'), ',') }} %){{ $orden->precios_incluian_iva ? ' incluido' : '' }}</dt>
                                <dd>{{ Formato::pesos($orden->iva) }}</dd>
                            </div>
                            <div class="flex justify-between border-t pt-1 text-base font-bold text-gray-900"><dt>Total</dt><dd>{{ Formato::pesos($orden->total) }}</dd></div>
                        </dl>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Historial de estados --}}
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-gray-900">Historial</h3>
                <ol class="mt-4 space-y-4 border-l border-gray-200 pl-5">
                    @forelse($orden->historialEstados as $fila)
                        <li class="relative">
                            <span class="absolute -left-[1.6rem] top-1.5 h-2.5 w-2.5 rounded-full ring-4 ring-white {{ $colorEvento[$fila->evento] ?? 'bg-gray-400' }}"></span>
                            <p class="text-sm font-medium text-gray-900">
                                {{ $eventos[$fila->evento] ?? $fila->evento }}
                                @if($fila->estado_anterior && $fila->estado_anterior !== $fila->estado_nuevo)
                                    <span class="font-normal text-gray-600">· {{ $etiquetas[$fila->estado_anterior] ?? $fila->estado_anterior }} → {{ $etiquetas[$fila->estado_nuevo] ?? $fila->estado_nuevo }}</span>
                                @elseif(! $fila->estado_anterior)
                                    <span class="font-normal text-gray-600">· {{ $etiquetas[$fila->estado_nuevo] ?? $fila->estado_nuevo }}</span>
                                @endif
                            </p>
                            <p class="text-xs text-gray-500">
                                {{ $fila->created_at->timezone('America/Bogota')->format('d/m/Y h:i a') }}
                                · {{ $fila->usuario?->name ?? 'Sistema' }}
                                @if($fila->mecanico) · Mecánico: {{ $fila->mecanico->nombre }} @endif
                            </p>
                            @if($fila->observaciones)
                                <p class="mt-1 whitespace-pre-line text-sm text-gray-700">
                                    @if($fila->evento === 'correccion')<span class="font-medium">Motivo:</span>@endif
                                    {{ $fila->observaciones }}
                                </p>
                            @endif
                        </li>
                    @empty
                        <li class="text-sm text-gray-500">Sin movimientos registrados.</li>
                    @endforelse
                </ol>
            </div>
        </div>
    </div>

    {{-- Corrección de factura: motivo + PIN (solo administrativos, solo en listo_para_reclamar). --}}
    @if($orden->estado === 'listo_para_reclamar')
        @can('corregirFactura', $orden)
            <form method="POST" action="{{ route('facturas.corregir', $orden) }}"
                  x-data="Object.assign(formularioConPin(), { pasoMotivo: false, motivo: '', errorMotivo: null })"
                  @abrir-correccion.window="pasoMotivo = true; errorMotivo = null; $nextTick(() => $refs.motivo.focus())"
                  @submit.prevent>
                @csrf
                <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
                    @include('partials.pin-modal', ['titulo' => "Corregir factura N.º {$orden->numero_factura}"])
                </div>

                <div x-show="pasoMotivo" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="titulo-correccion"
                     @keydown.escape.window="pasoMotivo = false">
                    <div class="absolute inset-0 bg-gray-900/50" @click="pasoMotivo = false"></div>
                    <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                        <h3 id="titulo-correccion" class="text-lg font-semibold text-gray-900">Corregir factura N.º {{ $orden->numero_factura }}</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            Se devuelve el stock de los repuestos, se anulan los valores y la orden vuelve a "Terminada"
                            para liquidarla de nuevo con el mismo número de factura.
                        </p>
                        <label for="motivo" class="mt-4 block text-sm font-medium text-gray-700">Motivo de la corrección <span class="text-red-600">*</span></label>
                        <textarea id="motivo" name="motivo" x-ref="motivo" x-model="motivo" rows="3" maxlength="1000"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                  placeholder="Ej.: se facturaron 2 filtros y se usó 1."></textarea>
                        <p class="mt-1 text-xs" :class="motivo.trim().length >= 10 ? 'text-gray-400' : 'text-amber-700'"
                           x-text="`${motivo.trim().length}/10 caracteres mínimo`"></p>
                        <p x-show="errorMotivo" x-text="errorMotivo" class="mt-1 text-sm text-red-600" role="alert"></p>
                        <div class="mt-6 flex justify-end gap-3">
                            <button type="button" @click="pasoMotivo = false" class="rounded-md border px-4 py-2 text-sm">Cancelar</button>
                            <button type="button"
                                    @click="if (motivo.trim().length < 10) { errorMotivo = 'Describe el motivo con al menos 10 caracteres.'; return; } pasoMotivo = false; pedirPin()"
                                    class="rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-500">
                                Continuar con PIN
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        @endcan
    @endif

    @if($orden->estado === 'terminada')
        @include('facturas._liquidacion-modal')
    @endif
    @if($orden->estado === 'listo_para_reclamar' && $usuario->can('marcarPagada', $orden))
        @include('ordenes._pago-modal')
    @endif
</x-app-layout>
