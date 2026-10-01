<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('inventario.index') }}" class="text-sm text-gray-500 hover:text-gray-800">← Inventario</a>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Distribuidores</h2>
            </div>
            <a href="{{ route('distribuidores.create') }}"
               class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                + Nuevo distribuidor
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-6xl space-y-4 sm:px-6 lg:px-8">
            @if(session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Distribuidor</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Contacto</th>
                            <th class="px-4 py-2 text-center text-xs font-medium uppercase text-gray-500">Repuestos</th>
                            <th class="px-4 py-2 text-center text-xs font-medium uppercase text-gray-500">En stock bajo</th>
                            <th class="px-4 py-2"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($distribuidores as $distribuidor)
                            <tr class="{{ $distribuidor->activo ? '' : 'bg-gray-50 text-gray-400' }}">
                                <td class="px-4 py-3">
                                    <p class="font-medium {{ $distribuidor->activo ? 'text-gray-900' : '' }}">
                                        {{ $distribuidor->nombre }}
                                        @unless($distribuidor->activo)
                                            <span class="ml-1 rounded-full bg-gray-200 px-2 py-0.5 text-xs text-gray-600">Inactivo</span>
                                        @endunless
                                    </p>
                                    @if($distribuidor->notas)
                                        <p class="text-xs text-gray-500">{{ Str::limit($distribuidor->notas, 80) }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    <p>{{ $distribuidor->contacto ?? '—' }}</p>
                                    @if($distribuidor->telefono_whatsapp)
                                        <p class="text-xs">
                                            {{ $distribuidor->telefono_whatsapp }}
                                            <a href="{{ $distribuidor->enlaceWhatsApp() }}" target="_blank" rel="noopener"
                                               class="ml-1 text-green-700 hover:underline">WhatsApp</a>
                                        </p>
                                    @endif
                                    @if($distribuidor->email)
                                        <p class="text-xs">{{ $distribuidor->email }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">{{ $distribuidor->repuestos_count }}</td>
                                <td class="px-4 py-3 text-center">
                                    @if($distribuidor->repuestos_bajos_count > 0)
                                        <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">
                                            {{ $distribuidor->repuestos_bajos_count }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">0</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    @if($distribuidor->activo && $distribuidor->repuestos_bajos_count > 0)
                                        <a href="{{ route('distribuidores.solicitud', $distribuidor) }}"
                                           class="mr-3 font-medium text-red-700 hover:text-red-900">Solicitar pedido</a>
                                    @endif
                                    <a href="{{ route('distribuidores.edit', $distribuidor) }}" class="text-indigo-600 hover:text-indigo-900">Editar</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                    No hay distribuidores registrados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
