<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('inventario.index') }}" class="text-sm text-gray-500 hover:text-gray-800">← Inventario</a>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Categorías de repuesto</h2>
            </div>
            <a href="{{ route('categorias.create') }}"
               class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                + Nueva categoría
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl space-y-4 sm:px-6 lg:px-8">
            @if(session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            <p class="text-sm text-gray-600">
                Las categorías son las secciones del inventario, en el orden indicado.
                No se eliminan: desactívalas para que no se ofrezcan en repuestos nuevos.
            </p>

            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Orden</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Nombre</th>
                            <th class="px-4 py-2 text-center text-xs font-medium uppercase text-gray-500">Repuestos</th>
                            <th class="px-4 py-2 text-center text-xs font-medium uppercase text-gray-500">Estado</th>
                            <th class="px-4 py-2"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($categorias as $categoria)
                            <tr class="{{ $categoria->activo ? '' : 'bg-gray-50 text-gray-400' }}">
                                <td class="px-4 py-2 text-gray-500">{{ $categoria->orden }}</td>
                                <td class="px-4 py-2 font-medium {{ $categoria->activo ? 'text-gray-900' : '' }}">{{ $categoria->nombre }}</td>
                                <td class="px-4 py-2 text-center">{{ $categoria->repuestos_count }}</td>
                                <td class="px-4 py-2 text-center">
                                    @if($categoria->activo)
                                        <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-700">Activa</span>
                                    @else
                                        <span class="rounded-full bg-gray-200 px-2 py-0.5 text-xs text-gray-600">Inactiva</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('categorias.edit', $categoria) }}" class="text-indigo-600 hover:text-indigo-900">Editar</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
