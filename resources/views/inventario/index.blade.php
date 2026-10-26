@php
    use App\Support\Formato;
    $puedeGestionar = auth()->user()->can('create', App\Models\Repuesto::class);
    $verCostos = auth()->user()->can('verCostos', App\Models\Repuesto::class);
    $puedePedir = auth()->user()->can('viewAny', App\Models\Distribuidor::class);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Inventario de Repuestos</h2>
            @if($puedeGestionar)
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('distribuidores.index') }}"
                       class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Distribuidores
                    </a>
                    <a href="{{ route('categorias.index') }}"
                       class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Categorías
                    </a>
                    <a href="{{ route('inventario.create') }}"
                       class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                        + Agregar repuesto
                    </a>
                </div>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">

            @if(session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            {{-- Alertas de stock bajo agrupadas por distribuidor --}}
            @if($totalAlertas > 0)
                <details class="group rounded-lg border border-red-200 bg-red-50" @if(! $filtrando) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between p-4">
                        <span class="text-sm font-semibold text-red-800">
                            ⚠ {{ $totalAlertas }} {{ $totalAlertas === 1 ? 'repuesto' : 'repuestos' }} en stock bajo
                            <span class="font-normal text-red-700">— el proveedor tarda de 2 a 5 días hábiles.</span>
                        </span>
                        <span class="text-xs text-red-700 group-open:hidden">Mostrar</span>
                        <span class="hidden text-xs text-red-700 group-open:inline">Ocultar</span>
                    </summary>

                    <div class="grid gap-4 px-4 pb-4 md:grid-cols-2 xl:grid-cols-3">
                        @foreach($alertas as $grupo)
                            @php
                                $distribuidor = $grupo['distribuidor'];
                                $diasEntrega = $distribuidor
                                    ? $grupo['repuestos']->first()->distribuidores->firstWhere('id', $distribuidor->id)?->pivot->tiempo_entrega_dias
                                    : null;
                            @endphp
                            <div class="flex flex-col rounded-md border border-red-100 bg-white p-4">
                                <div class="mb-2 flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">
                                            {{ $distribuidor?->nombre ?? 'Sin distribuidor asignado' }}
                                        </p>
                                        @if($diasEntrega)
                                            <p class="text-xs text-gray-500">Entrega en {{ $diasEntrega }} {{ $diasEntrega == 1 ? 'día' : 'días' }}</p>
                                        @endif
                                    </div>
                                    <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">
                                        {{ $grupo['repuestos']->count() }}
                                    </span>
                                </div>

                                <ul class="mb-3 flex-1 space-y-1 text-sm text-gray-700">
                                    @foreach($grupo['repuestos']->take(8) as $repuesto)
                                        <li>
                                            <span class="font-medium">{{ $repuesto->nombre }}</span>
                                            @if($repuesto->modelo_compatible)
                                                <span class="text-gray-500">para {{ $repuesto->modelo_compatible }}</span>
                                            @endif
                                            — <span class="font-semibold text-red-700">
                                                {{ $repuesto->stock_actual === 0 ? 'Agotado' : 'Quedan ' . $repuesto->stock_actual }}
                                            </span>
                                        </li>
                                    @endforeach
                                    @if($grupo['repuestos']->count() > 8)
                                        <li class="text-xs text-gray-500">y {{ $grupo['repuestos']->count() - 8 }} más…</li>
                                    @endif
                                </ul>

                                @if($puedePedir)
                                    @if($distribuidor)
                                        <a href="{{ route('distribuidores.solicitud', $distribuidor) }}"
                                           class="rounded-md bg-red-600 px-3 py-2 text-center text-sm font-semibold text-white hover:bg-red-500">
                                            Solicitar repuestos a este distribuidor
                                        </a>
                                    @else
                                        <p class="text-xs text-gray-500">
                                            Asigna un distribuidor desde la edición de cada repuesto para poder pedirlos.
                                        </p>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    </div>
                </details>
            @endif

            {{-- Buscador --}}
            <div class="rounded-lg bg-white p-4 shadow-sm">
                <form method="GET" class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[200px] flex-1">
                        <label for="buscar" class="block text-sm font-medium text-gray-700">Buscar</label>
                        <input type="search" name="buscar" id="buscar" value="{{ $buscar }}" autofocus
                               placeholder="Nombre, marca, SKU, referencia o moto (ej. Duke 200)"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div class="flex items-center gap-2 pb-2">
                        <input type="checkbox" name="solo_stock_bajo" id="solo_stock_bajo" value="1"
                               @checked($soloStockBajo) class="rounded border-gray-300">
                        <label for="solo_stock_bajo" class="text-sm text-gray-700">Solo stock bajo</label>
                    </div>
                    <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm text-white">Filtrar</button>
                    @if($filtrando)
                        <a href="{{ route('inventario.index') }}" class="px-2 py-2 text-sm text-gray-600 hover:text-gray-900">Limpiar</a>
                    @endif
                </form>
            </div>

            {{-- Listado por categoría --}}
            <div x-data class="flex items-center justify-between text-sm text-gray-600">
                <p>
                    {{ $totalRepuestos }} {{ $totalRepuestos === 1 ? 'repuesto' : 'repuestos' }}{{ $filtrando ? ($totalRepuestos === 1 ? ' encontrado' : ' encontrados') : '' }}
                    en {{ $grupos->count() }} {{ $grupos->count() === 1 ? 'categoría' : 'categorías' }}
                </p>
                @if($grupos->isNotEmpty())
                    <div class="flex gap-3">
                        <button type="button" class="hover:text-gray-900"
                                @click="document.querySelectorAll('details[data-categoria]').forEach(d => d.open = true)">
                            Expandir todo
                        </button>
                        <button type="button" class="hover:text-gray-900"
                                @click="document.querySelectorAll('details[data-categoria]').forEach(d => d.open = false)">
                            Contraer todo
                        </button>
                    </div>
                @endif
            </div>

            @forelse($grupos as $grupo)
                @php
                    $bajos = $grupo['repuestos']->filter->estaEnStockBajo()->count();
                @endphp
                <details data-categoria class="group overflow-hidden rounded-lg bg-white shadow-sm" @if($filtrando) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 hover:bg-gray-50">
                        <span class="flex items-center gap-2">
                            <span class="text-gray-400 transition group-open:rotate-90">▸</span>
                            <span class="font-semibold text-gray-800">{{ $grupo['categoria']?->nombre ?? 'Sin categoría' }}</span>
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ $grupo['repuestos']->count() }}</span>
                        </span>
                        @if($bajos > 0)
                            <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">
                                {{ $bajos }} en stock bajo
                            </span>
                        @endif
                    </summary>

                    <div class="overflow-x-auto border-t border-gray-100">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Repuesto</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Sirve para</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Ubicación</th>
                                    @if($verCostos)
                                        <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Costo</th>
                                    @endif
                                    <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Precio venta</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium uppercase text-gray-500">Stock</th>
                                    @if($puedeGestionar)
                                        <th class="px-4 py-2"><span class="sr-only">Acciones</span></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($grupo['repuestos'] as $repuesto)
                                    @php($bajo = $repuesto->estaEnStockBajo())
                                    <tr class="{{ $bajo ? 'bg-red-50' : '' }}">
                                        <td class="px-4 py-2">
                                            <p class="font-medium text-gray-900">{{ $repuesto->nombre }}</p>
                                            <p class="text-xs text-gray-500">
                                                {{ collect([$repuesto->codigo_sku, $repuesto->referencia_proveedor ? 'Ref. ' . $repuesto->referencia_proveedor : null, $repuesto->marca])->filter()->implode(' · ') }}
                                            </p>
                                        </td>
                                        <td class="px-4 py-2 text-gray-600">{{ $repuesto->modelo_compatible ?? '—' }}</td>
                                        <td class="px-4 py-2 text-gray-600">{{ $repuesto->ubicacion_estante ?? '—' }}</td>
                                        @if($verCostos)
                                            <td class="px-4 py-2 text-right text-gray-500">{{ Formato::pesos($repuesto->precio_costo) }}</td>
                                        @endif
                                        <td class="px-4 py-2 text-right text-gray-700">{{ Formato::pesos($repuesto->precio_venta) }}</td>
                                        <td class="whitespace-nowrap px-4 py-2 text-center">
                                            <span class="{{ $bajo ? 'font-semibold text-red-700' : 'text-gray-700' }}">{{ $repuesto->stock_actual }}</span>
                                            <span class="text-xs text-gray-400">/ mín. {{ $repuesto->stock_minimo }}</span>
                                        </td>
                                        @if($puedeGestionar)
                                            <td class="px-4 py-2 text-right">
                                                <a href="{{ route('inventario.edit', $repuesto) }}" class="text-indigo-600 hover:text-indigo-900">Editar</a>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @empty
                <div class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow-sm">
                    @if($filtrando)
                        No hay repuestos que coincidan con la búsqueda.
                    @else
                        El inventario está vacío.
                    @endif
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
