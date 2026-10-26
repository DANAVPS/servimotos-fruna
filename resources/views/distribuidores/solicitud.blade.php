@php
    use App\Support\Formato;

    $linea = fn ($repuesto, bool $preferido) => [
        'id' => $repuesto->id,
        'cantidad' => $preferido ? $repuesto->cantidadSugeridaPedido() : 0,
        'costo' => (float) ($repuesto->pivot->precio_costo_proveedor ?? $repuesto->precio_costo),
    ];
    $lineas = $preferidos->map(fn ($r) => $linea($r, true))
        ->concat($otros->map(fn ($r) => $linea($r, false)))
        ->values();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <button type="button" onclick="history.length > 1 ? history.back() : location.assign('{{ route('inventario.index') }}')"
                    class="text-sm text-gray-500 hover:text-gray-800">← Volver</button>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Solicitud de pedido — {{ $distribuidor->nombre }}</h2>
        </div>
    </x-slot>

    <div class="py-6"
         x-data="solicitudPedido({
            lineas: @js($lineas),
            urlWhatsapp: @js(route('distribuidores.solicitud.whatsapp', $distribuidor)),
         })">
        <div class="mx-auto max-w-5xl space-y-4 sm:px-6 lg:px-8">

            @if($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">
                    @foreach($errors->all() as $error) <p>{{ $error }}</p> @endforeach
                </div>
            @endif

            <div class="rounded-lg bg-white p-4 text-sm text-gray-600 shadow-sm">
                <p>
                    <span class="font-medium text-gray-900">{{ $distribuidor->contacto ?? $distribuidor->nombre }}</span>
                    @if($distribuidor->telefono_whatsapp) · WhatsApp {{ $distribuidor->telefono_whatsapp }} @endif
                    @if($distribuidor->email) · {{ $distribuidor->email }} @endif
                </p>
                <p class="mt-1 text-xs text-gray-500">
                    La cantidad sugerida repone hasta el doble del stock mínimo. Ajústala o pon 0 para no pedir un repuesto.
                </p>
            </div>

            <form method="POST" action="{{ route('distribuidores.solicitud.pdf', $distribuidor) }}" target="_blank" x-ref="formulario">
                @csrf

                @if($preferidos->isEmpty() && $otros->isEmpty())
                    <div class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow-sm">
                        Este distribuidor no tiene repuestos en stock bajo.
                    </div>
                @endif

                @foreach([
                    ['titulo' => 'Repuestos en stock bajo', 'repuestos' => $preferidos, 'nota' => null],
                    ['titulo' => 'También los surte este distribuidor', 'repuestos' => $otros, 'nota' => 'Por defecto se piden a otro distribuidor que entrega más rápido.'],
                ] as $seccion)
                    @continue($seccion['repuestos']->isEmpty())

                    <div class="mb-4 overflow-x-auto rounded-lg bg-white shadow-sm">
                        <div class="border-b px-4 py-3">
                            <h3 class="text-sm font-semibold text-gray-800">{{ $seccion['titulo'] }}</h3>
                            @if($seccion['nota']) <p class="text-xs text-gray-500">{{ $seccion['nota'] }}</p> @endif
                        </div>
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Repuesto</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Código proveedor</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium uppercase text-gray-500">Stock</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Costo unit.</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium uppercase text-gray-500">Cantidad</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($seccion['repuestos'] as $repuesto)
                                    <tr>
                                        <td class="px-4 py-2">
                                            <p class="font-medium text-gray-900">{{ $repuesto->nombre }}</p>
                                            @if($repuesto->modelo_compatible)
                                                <p class="text-xs text-gray-500">Para {{ $repuesto->modelo_compatible }}</p>
                                            @endif
                                        </td>
                                        @php($codigo = \App\Services\SolicitudPedidoService::codigoParaProveedor($repuesto))
                                        <td class="px-4 py-2 text-gray-600">
                                            @if($codigo)
                                                {{ $codigo }}
                                            @else
                                                <span class="font-semibold text-amber-700">{{ \App\Services\SolicitudPedidoService::SIN_CODIGO }}</span>
                                                <a href="{{ route('inventario.edit', $repuesto) }}#distribuidores" target="_blank"
                                                   class="block text-xs text-indigo-600 hover:underline">Agregar código</a>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-2 text-center">
                                            <span class="font-semibold text-red-700">{{ $repuesto->stock_actual }}</span>
                                            <span class="text-xs text-gray-400">/ mín. {{ $repuesto->stock_minimo }}</span>
                                        </td>
                                        <td class="px-4 py-2 text-right text-gray-600">
                                            {{ Formato::pesos($repuesto->pivot->precio_costo_proveedor ?? $repuesto->precio_costo) }}
                                        </td>
                                        <td class="px-4 py-2 text-center">
                                            <input type="number" min="0" max="9999" step="1"
                                                   name="cantidades[{{ $repuesto->id }}]"
                                                   x-model.number="cantidad[{{ $repuesto->id }}]"
                                                   class="w-20 rounded-md border-gray-300 text-center text-sm shadow-sm">
                                        </td>
                                        <td class="px-4 py-2 text-right text-gray-700" x-text="pesos(subtotal({{ $repuesto->id }}))"></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach

                @if($preferidos->isNotEmpty() || $otros->isNotEmpty())
                    <div class="flex flex-wrap items-center justify-between gap-4 rounded-lg bg-white p-4 shadow-sm">
                        <p class="text-sm text-gray-700">
                            <span x-text="referencias"></span> referencias ·
                            <span x-text="unidades"></span> unidades ·
                            Total estimado <span class="font-semibold" x-text="pesos(total)"></span>
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" :disabled="referencias === 0"
                                    class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-40">
                                Generar PDF de pedido
                            </button>
                            <button type="button" @click="generarWhatsapp()" :disabled="referencias === 0 || cargando"
                                    class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-500 disabled:opacity-40">
                                <span x-show="! cargando">Generar texto WhatsApp</span>
                                <span x-show="cargando" x-cloak>Generando…</span>
                            </button>
                        </div>
                    </div>
                @endif
            </form>
        </div>

        {{-- Modal con el mensaje listo para pegar en WhatsApp Web --}}
        <div x-show="modal" x-cloak @keydown.escape.window="modal = false"
             class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="titulo-whatsapp">
            <div class="absolute inset-0 bg-gray-900/50" @click="modal = false"></div>
            <div class="relative w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                <h3 id="titulo-whatsapp" class="text-lg font-semibold text-gray-900">Mensaje para {{ $distribuidor->nombre }}</h3>
                <p class="mt-1 text-xs text-gray-500">No incluye costos: el distribuidor confirma disponibilidad y precio.</p>
                <textarea x-ref="texto" x-model="texto" rows="14" readonly
                          class="mt-3 block w-full rounded-md border-gray-300 font-mono text-sm shadow-sm"></textarea>
                <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
                    <span x-show="copiado" x-cloak class="mr-auto text-sm text-green-700">✓ Copiado</span>
                    <button type="button" @click="modal = false" class="rounded-md border px-4 py-2 text-sm">Cerrar</button>
                    <a x-show="enlace" x-cloak :href="enlace" target="_blank" rel="noopener"
                       class="rounded-md border border-green-600 px-4 py-2 text-sm font-medium text-green-700 hover:bg-green-50">
                        Abrir en WhatsApp
                    </a>
                    <button type="button" @click="copiar()" class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-500">
                        Copiar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function solicitudPedido({ lineas, urlWhatsapp }) {
            return {
                cantidad: Object.fromEntries(lineas.map(l => [l.id, l.cantidad])),
                costos: Object.fromEntries(lineas.map(l => [l.id, l.costo])),
                modal: false,
                cargando: false,
                copiado: false,
                texto: '',
                enlace: null,

                subtotal(id) { return (Number(this.cantidad[id]) || 0) * this.costos[id]; },
                get referencias() { return Object.values(this.cantidad).filter(c => Number(c) > 0).length; },
                get unidades() { return Object.values(this.cantidad).reduce((suma, c) => suma + (Number(c) > 0 ? Number(c) : 0), 0); },
                get total() { return Object.keys(this.cantidad).reduce((suma, id) => suma + this.subtotal(id), 0); },
                pesos(valor) { return '$' + Math.round(valor).toLocaleString('es-CO'); },

                async generarWhatsapp() {
                    this.cargando = true;
                    try {
                        const respuesta = await fetch(urlWhatsapp, {
                            method: 'POST',
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            body: new FormData(this.$refs.formulario),
                        });
                        const datos = await respuesta.json();

                        if (! respuesta.ok) {
                            alert(datos.message ?? 'No se pudo generar el mensaje.');
                            return;
                        }

                        this.texto = datos.texto;
                        this.enlace = datos.enlace;
                        this.copiado = false;
                        this.modal = true;
                    } catch (e) {
                        alert('No se pudo conectar con el servidor. Intenta de nuevo.');
                    } finally {
                        this.cargando = false;
                    }
                },

                async copiar() {
                    try {
                        await navigator.clipboard.writeText(this.texto);
                    } catch (e) {
                        // Sin HTTPS (ej. IP de la red local) no hay API de portapapeles.
                        this.$refs.texto.select();
                        document.execCommand('copy');
                    }
                    this.copiado = true;
                },
            };
        }
    </script>
</x-app-layout>
