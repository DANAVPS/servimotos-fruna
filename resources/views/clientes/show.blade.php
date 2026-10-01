<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" onclick="history.length > 1 ? history.back() : location.assign('{{ route('clientes.index') }}')"
                        class="text-sm text-gray-500 hover:text-gray-800">← Volver</button>
                <h2 class="text-xl font-semibold text-gray-800">{{ $cliente->nombre }}</h2>
                @include('clientes._estado', ['estado' => $cliente->estado_cliente])
            </div>
            @can('update', $cliente)
                <div class="flex gap-2">
                    @if($cliente->estado_cliente === 'archivado')
                        <form method="POST" action="{{ route('clientes.reinscribir', $cliente) }}"
                              x-data="formularioConPin()" @submit.prevent="pedirPin()">
                            @csrf
                            @include('partials.pin-modal', ['titulo' => "Reinscribir a {$cliente->nombre}"])
                            <button type="submit" class="rounded-md border border-green-600 px-4 py-2 text-sm font-medium text-green-700 hover:bg-green-50">
                                Reinscribir
                            </button>
                        </form>
                    @endif
                    <a href="{{ route('clientes.edit', $cliente) }}"
                       class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Editar</a>
                </div>
            @endcan
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">

            @if(session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                    <div><dt class="text-gray-500">Teléfono</dt><dd class="font-medium">{{ $cliente->telefono }}</dd></div>
                    <div><dt class="text-gray-500">Cédula / NIT</dt><dd class="font-medium">{{ $cliente->cedula_nit ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Correo</dt><dd class="font-medium">{{ $cliente->correo ?? '—' }}</dd></div>
                    <div>
                        <dt class="text-gray-500">Tipo de cliente</dt>
                        <dd class="mt-0.5">@include('clientes._tipo', ['tipo' => $cliente->tipoClienteEfectivo()])</dd>
                    </div>
                    <div><dt class="text-gray-500">Última visita</dt><dd class="font-medium">{{ $cliente->fecha_ultima_visita?->format('d/m/Y') ?? '—' }}</dd></div>
                </dl>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="mb-3 text-sm font-semibold text-gray-700">Motos registradas</h3>
                <ul class="divide-y divide-gray-200">
                    @forelse($cliente->motos as $moto)
                        <li class="py-2 text-sm">
                            <span class="font-mono font-semibold">{{ $moto->placa }}</span>
                            — {{ $moto->marca }} {{ $moto->modelo }}{{ $moto->anio ? " ({$moto->anio})" : '' }}
                        </li>
                    @empty
                        <li class="py-2 text-sm text-gray-400">Sin motos registradas.</li>
                    @endforelse
                </ul>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="mb-3 text-sm font-semibold text-gray-700">Últimas órdenes de servicio</h3>
                <ul class="divide-y divide-gray-200">
                    @forelse($cliente->ordenesServicio as $orden)
                        <li class="py-2 text-sm">
                            <a href="{{ route('ordenes.show', $orden) }}" class="hover:text-indigo-600">
                                Orden #{{ $orden->id }} — {{ ucwords(str_replace('_', ' ', $orden->estado)) }}
                                — {{ $orden->fecha_hora_ingreso->format('d/m/Y') }}
                            </a>
                        </li>
                    @empty
                        <li class="py-2 text-sm text-gray-400">Sin órdenes registradas.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
