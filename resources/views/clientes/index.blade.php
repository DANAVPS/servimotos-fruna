@php
    $puedeCrear = auth()->user()->can('create', App\Models\Cliente::class);
    // El mecánico no ve contacto (cédula, teléfono) ni tipo/descuento.
    $verPrivado = App\Support\TarjetaOrden::veDinero(auth()->user());
    $filtrando = request()->filled('buscar') || request()->filled('estado') || request()->filled('tipo_cliente_id');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold text-gray-800">Clientes</h2>
            <div class="flex flex-wrap gap-2">
                @can('viewAny', App\Models\TipoCliente::class)
                    <a href="{{ route('tipos-cliente.index') }}"
                       class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Tipos de cliente
                    </a>
                @endcan
                @if($puedeCrear)
                    <a href="{{ route('clientes.create') }}"
                       class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                        + Nuevo cliente
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-6xl space-y-4 sm:px-6 lg:px-8">

            @if(session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            <div class="rounded-lg bg-white p-4 shadow-sm">
                <form method="GET" class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[220px] flex-1">
                        <label for="buscar" class="block text-sm font-medium text-gray-700">Buscar</label>
                        <input type="search" name="buscar" id="buscar" value="{{ request('buscar') }}" autofocus
                               placeholder="{{ $verPrivado ? 'Nombre, teléfono, cédula/NIT o placa (ej. ABC12D)' : 'Nombre o placa (ej. ABC12D)' }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <label for="estado" class="block text-sm font-medium text-gray-700">Estado</label>
                        <select name="estado" id="estado" class="mt-1 block rounded-md border-gray-300 shadow-sm">
                            <option value="">Activos e hibernando</option>
                            <option value="activo" @selected(request('estado') === 'activo')>Solo activos</option>
                            <option value="hibernando" @selected(request('estado') === 'hibernando')>Hibernando</option>
                            <option value="archivado" @selected(request('estado') === 'archivado')>Archivados</option>
                        </select>
                    </div>
                    @if($verPrivado)
                    <div>
                        <label for="tipo_cliente_id" class="block text-sm font-medium text-gray-700">Tipo</label>
                        <select name="tipo_cliente_id" id="tipo_cliente_id" class="mt-1 block rounded-md border-gray-300 shadow-sm">
                            <option value="">Todos</option>
                            @foreach($tiposCliente as $tipo)
                                <option value="{{ $tipo->id }}" @selected((string) request('tipo_cliente_id') === (string) $tipo->id)>
                                    {{ $tipo->nombre }}{{ $tipo->activo ? '' : ' (inactivo)' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm text-white">Filtrar</button>
                    @if($filtrando)
                        <a href="{{ route('clientes.index') }}" class="px-2 py-2 text-sm text-gray-600 hover:text-gray-900">Limpiar</a>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Cliente</th>
                            @if($verPrivado)
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Teléfono</th>
                            @endif
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Motos</th>
                            @if($verPrivado)
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Tipo</th>
                            @endif
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Estado</th>
                            @if($puedeCrear)
                                <th class="px-4 py-3"><span class="sr-only">Acciones</span></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($clientes as $cliente)
                            <tr>
                                <td class="px-4 py-3">
                                    {{-- Quien puede editar va directo a la edición; el mecánico, a la ficha. --}}
                                    <a href="{{ auth()->user()->can('update', $cliente) ? route('clientes.edit', $cliente) : route('clientes.show', $cliente) }}"
                                       class="font-medium text-gray-900 hover:text-indigo-600">{{ $verPrivado ? $cliente->nombre : App\Support\TarjetaOrden::primerNombre($cliente->nombre) }}</a>
                                    @if($verPrivado && $cliente->cedula_nit)
                                        <p class="text-xs text-gray-500">CC/NIT {{ $cliente->cedula_nit }}</p>
                                    @endif
                                </td>
                                @if($verPrivado)
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ $cliente->telefono }}</td>
                                @endif
                                <td class="px-4 py-3 text-gray-600">
                                    @foreach($cliente->motos as $moto)
                                        <span class="font-mono font-medium text-gray-800">{{ $moto->placa }}</span>
                                        <span class="text-xs text-gray-500">{{ $moto->marca }} {{ $moto->modelo }}</span>@if(! $loop->last)<br>@endif
                                    @endforeach
                                </td>
                                @if($verPrivado)
                                    <td class="px-4 py-3">@include('clientes._tipo', ['tipo' => $cliente->tipoCliente])</td>
                                @endif
                                <td class="px-4 py-3">@include('clientes._estado', ['estado' => $cliente->estado_cliente])</td>
                                @if($puedeCrear)
                                    <td class="whitespace-nowrap px-4 py-3 text-right">
                                        @can('update', $cliente)
                                            @if($cliente->estado_cliente === 'archivado')
                                                <form method="POST" action="{{ route('clientes.reinscribir', $cliente) }}" class="mr-3 inline text-left"
                                                      x-data="formularioConPin()" @submit.prevent="pedirPin()">
                                                    @csrf
                                                    @include('partials.pin-modal', ['titulo' => "Reinscribir a {$cliente->nombre}"])
                                                    <button type="submit" class="text-green-600 hover:text-green-900">Reinscribir</button>
                                                </form>
                                            @endif
                                            <a href="{{ route('clientes.edit', $cliente) }}" class="text-indigo-600 hover:text-indigo-900">Editar</a>
                                        @endcan
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    {{ $filtrando ? 'No hay clientes que coincidan con la búsqueda.' : 'Aún no hay clientes registrados.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $clientes->links() }}</div>
        </div>
    </div>
</x-app-layout>
