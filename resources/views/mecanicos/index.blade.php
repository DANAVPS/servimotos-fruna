<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Mecánicos</h2>
            @can('create', App\Models\Mecanico::class)
                <a href="{{ route('mecanicos.create') }}"
                   class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                    + Nuevo mecánico
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-5xl space-y-4 sm:px-6 lg:px-8">
            @if(session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            <p class="text-sm text-gray-600">
                Los titulares responden por la orden y su mano de obra cuenta para comisiones; los ayudantes trabajan con un titular.
                No se eliminan: desactívalos para que no aparezcan al asignar órdenes.
            </p>

            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Nombre</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Tipo</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Titular / Ayudantes</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Teléfono</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Usuario</th>
                            <th class="px-4 py-2 text-center text-xs font-medium uppercase text-gray-500">Estado</th>
                            <th class="px-4 py-2"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($mecanicos as $mecanico)
                            <tr class="{{ $mecanico->activo ? '' : 'bg-gray-50 text-gray-400' }}">
                                <td class="px-4 py-2 font-medium {{ $mecanico->activo ? 'text-gray-900' : '' }}">{{ $mecanico->nombre }}</td>
                                <td class="px-4 py-2">
                                    @if($mecanico->esTitular())
                                        <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs text-indigo-700">Titular</span>
                                    @else
                                        <span class="rounded-full bg-sky-100 px-2 py-0.5 text-xs text-sky-700">Ayudante</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-gray-600">
                                    @if($mecanico->esAyudante())
                                        {{ $mecanico->principal?->nombre ?? 'Sin titular' }}
                                        @if($mecanico->principal && ! $mecanico->principal->activo)
                                            <span class="text-xs text-amber-600">(inactivo)</span>
                                        @endif
                                    @elseif($mecanico->subordinados_count)
                                        {{ $mecanico->subordinados_count }} {{ $mecanico->subordinados_count === 1 ? 'ayudante' : 'ayudantes' }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-gray-600">{{ $mecanico->telefono ?? '—' }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $mecanico->usuario?->email ?? 'Sin vincular' }}</td>
                                <td class="px-4 py-2 text-center">
                                    @if($mecanico->activo)
                                        <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-700">Activo</span>
                                    @else
                                        <span class="rounded-full bg-gray-200 px-2 py-0.5 text-xs text-gray-600">Inactivo</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('mecanicos.edit', $mecanico) }}" class="text-indigo-600 hover:text-indigo-900">Editar</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">Aún no hay mecánicos registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
