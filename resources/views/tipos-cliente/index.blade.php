<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('clientes.index') }}" class="text-sm text-gray-500 hover:text-gray-800">← Clientes</a>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Tipos de cliente</h2>
            </div>
            <a href="{{ route('tipos-cliente.create') }}"
               class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                + Nuevo tipo
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl space-y-4 sm:px-6 lg:px-8">
            @if(session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif
            @if(session('advertencia'))
                <div class="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800">⚠ {{ session('advertencia') }}</div>
            @endif

            <p class="text-sm text-gray-600">
                El descuento se aplica automáticamente a los <strong>repuestos</strong> al liquidar una orden del cliente
                (la mano de obra no cambia). Cada factura guarda el % aplicado, así que cambiarlo aquí no altera facturas anteriores.
                Los tipos no se eliminan: desactivados no se pueden asignar a clientes nuevos, pero quienes ya los tienen conservan su descuento.
            </p>

            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Tipo</th>
                            <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Descuento en repuestos</th>
                            <th class="px-4 py-2 text-center text-xs font-medium uppercase text-gray-500">Clientes</th>
                            <th class="px-4 py-2 text-center text-xs font-medium uppercase text-gray-500">Estado</th>
                            <th class="px-4 py-2"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($tipos as $tipo)
                            <tr class="{{ $tipo->activo ? '' : 'bg-gray-50 text-gray-400' }}">
                                <td class="px-4 py-3">
                                    <p class="font-medium {{ $tipo->activo ? 'text-gray-900' : '' }}">
                                        {{ $tipo->nombre }}
                                        @if($tipo->esRegular())
                                            <span class="ml-1 text-xs font-normal text-gray-500">(por defecto)</span>
                                        @endif
                                    </p>
                                    @if($tipo->descripcion)
                                        <p class="text-xs text-gray-500">{{ $tipo->descripcion }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-semibold">
                                    {{ $tipo->tieneDescuento() ? rtrim(rtrim($tipo->descuento_repuestos_pct, '0'), '.') . '%' : '—' }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <a href="{{ route('clientes.index', ['tipo_cliente_id' => $tipo->id]) }}" class="hover:text-indigo-600">
                                        {{ $tipo->clientes_count }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($tipo->activo)
                                        <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-700">Activo</span>
                                    @else
                                        <span class="rounded-full bg-gray-200 px-2 py-0.5 text-xs text-gray-600">Inactivo</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('tipos-cliente.edit', $tipo) }}" class="text-indigo-600 hover:text-indigo-900">Editar</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
