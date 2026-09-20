<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">{{ $cliente->nombre }}</h2></x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8 space-y-6">

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-gray-500">Teléfono</dt><dd class="font-medium">{{ $cliente->telefono }}</dd></div>
                    <div><dt class="text-gray-500">Correo</dt><dd class="font-medium">{{ $cliente->correo ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Estado</dt><dd class="font-medium">{{ ucfirst($cliente->estado_cliente) }}</dd></div>
                    <div><dt class="text-gray-500">Última visita</dt><dd class="font-medium">{{ $cliente->fecha_ultima_visita?->format('d/m/Y') ?? '—' }}</dd></div>
                </dl>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="mb-3 text-sm font-semibold text-gray-700">Motos registradas</h3>
                <ul class="divide-y divide-gray-200">
                    @foreach($cliente->motos as $moto)
                        <li class="py-2 text-sm">{{ $moto->placa }} — {{ $moto->marca }} {{ $moto->modelo }} ({{ $moto->anio }})</li>
                    @endforeach
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
