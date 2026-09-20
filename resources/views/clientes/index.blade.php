<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800">Clientes</h2>
            <a href="{{ route('clientes.create') }}"
               class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                + Nuevo cliente
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-6xl sm:px-6 lg:px-8">

            @if(session('status'))
                <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            <div class="mb-4 rounded-lg bg-white p-4 shadow-sm">
                <form method="GET" class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700">Buscar</label>
                        <input type="text" name="buscar" value="{{ request('buscar') }}"
                               placeholder="Nombre o teléfono..."
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Estado</label>
                        <select name="estado" class="mt-1 block rounded-md border-gray-300 shadow-sm">
                            <option value="">Activos</option>
                            <option value="hibernando" @selected(request('estado') === 'hibernando')>Hibernando</option>
                            <option value="archivado" @selected(request('estado') === 'archivado')>Archivados</option>
                        </select>
                    </div>
                    <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm text-white">Filtrar</button>
                </form>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Nombre</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Teléfono</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Motos</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Estado</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase text-gray-500">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($clientes as $cliente)
                            <tr>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                    <a href="{{ route('clientes.show', $cliente) }}" class="hover:text-indigo-600">{{ $cliente->nombre }}</a>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $cliente->telefono }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $cliente->motos->pluck('placa')->join(', ') }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium
                                        {{ match($cliente->estado_cliente) {
                                            'activo' => 'bg-green-100 text-green-700',
                                            'hibernando' => 'bg-yellow-100 text-yellow-700',
                                            'archivado' => 'bg-gray-200 text-gray-600',
                                        } }}">
                                        {{ ucfirst($cliente->estado_cliente) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right text-sm">
                                    @if($cliente->estado_cliente === 'archivado')
                                        <form method="POST" action="{{ route('clientes.reinscribir', $cliente) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-green-600 hover:text-green-900">Reinscribir</button>
                                        </form>
                                    @else
                                        <a href="{{ route('clientes.edit', $cliente) }}" class="text-indigo-600 hover:text-indigo-900">Editar</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $clientes->links() }}</div>
        </div>
    </div>
</x-app-layout>
