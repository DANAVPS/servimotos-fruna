<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Inventario de Repuestos</h2>
            <a href="{{ route('inventario.create') }}"
               class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                + Agregar repuesto
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">

            @if(session('status'))
                <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            @if($alertas->count() > 0)
                <div class="mb-4 rounded-md border border-red-200 bg-red-50 p-4">
                    <p class="text-sm font-semibold text-red-800">
                        ⚠ {{ $alertas->count() }} repuesto(s) con stock bajo — riesgo de desabastecimiento
                        (proveedor tarda 2–5 días hábiles).
                    </p>
                    <ul class="mt-2 list-inside list-disc text-sm text-red-700">
                        @foreach($alertas as $alerta)
                            <li>{{ $alerta->nombre }} — quedan {{ $alerta->stock_actual }} unidades (mínimo: {{ $alerta->stock_minimo }})</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mb-4 rounded-lg bg-white p-4 shadow-sm">
                <form method="GET" class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700">Buscar</label>
                        <input type="text" name="buscar" value="{{ request('buscar') }}"
                               placeholder="Nombre o marca..."
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="solo_stock_bajo" id="solo_stock_bajo" value="1"
                               @checked(request()->boolean('solo_stock_bajo'))
                               class="rounded border-gray-300">
                        <label for="solo_stock_bajo" class="text-sm text-gray-700">Solo stock bajo</label>
                    </div>
                    <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm text-white">
                        Filtrar
                    </button>
                </form>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Repuesto</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Marca</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase text-gray-500">Precio venta</th>
                            <th class="px-6 py-3 text-center text-xs font-medium uppercase text-gray-500">Stock</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase text-gray-500">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($repuestos as $repuesto)
                            <tr class="{{ $repuesto->stock_actual <= $repuesto->stock_minimo ? 'bg-red-50' : '' }}">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $repuesto->nombre }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $repuesto->marca }}</td>
                                <td class="px-6 py-4 text-right text-sm text-gray-700">
                                    ${{ number_format($repuesto->precio_venta, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-center text-sm">
                                    <span class="{{ $repuesto->stock_actual <= $repuesto->stock_minimo ? 'font-semibold text-red-700' : 'text-gray-700' }}">
                                        {{ $repuesto->stock_actual }}
                                    </span>
                                    <span class="text-gray-400">/ mín. {{ $repuesto->stock_minimo }}</span>
                                </td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <a href="{{ route('inventario.edit', $repuesto) }}" class="text-indigo-600 hover:text-indigo-900">Editar</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $repuestos->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
