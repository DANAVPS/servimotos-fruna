<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Orden #{{ $orden->id }} — {{ $orden->moto->placa }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">

            @if(session('status'))
                <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-gray-500">Cliente</dt><dd class="font-medium">{{ $orden->cliente->nombre }}</dd></div>
                    <div><dt class="text-gray-500">Moto</dt><dd class="font-medium">{{ $orden->moto->marca }} {{ $orden->moto->modelo }}</dd></div>
                    <div><dt class="text-gray-500">Estado</dt><dd class="font-medium">{{ ucwords(str_replace('_', ' ', $orden->estado)) }}</dd></div>
                    <div><dt class="text-gray-500">Mecánico</dt><dd class="font-medium">{{ $orden->mecanico?->nombre ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Total</dt><dd class="font-medium">${{ number_format($orden->total, 0, ',', '.') }}</dd></div>
                </dl>

                <div class="mt-6 flex gap-3">
                    @if(in_array($orden->estado, ['terminada']))
                        <a href="{{ route('facturas.liquidar', $orden) }}"
                           class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">
                            Liquidar y generar factura
                        </a>
                    @endif

                    @if($orden->total > 0)
                        <a href="{{ route('facturas.pdf', $orden) }}" target="_blank"
                           class="rounded-md border px-4 py-2 text-sm">
                            Ver factura PDF
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
