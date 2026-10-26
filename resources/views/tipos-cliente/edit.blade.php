<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('tipos-cliente.index') }}" class="text-sm text-gray-500 hover:text-gray-800">← Tipos de cliente</a>
            <h2 class="text-xl font-semibold text-gray-800">Editar tipo: {{ $tipoCliente->nombre }}</h2>
        </div>
    </x-slot>
    <div class="py-6">
        <div class="mx-auto max-w-2xl space-y-4 sm:px-6 lg:px-8">
            @if($tipoCliente->clientes_count > 0)
                <p class="rounded-md bg-blue-50 p-3 text-sm text-blue-800">
                    {{ $tipoCliente->clientes_count }} {{ $tipoCliente->clientes_count === 1 ? 'cliente tiene' : 'clientes tienen' }} este tipo.
                    Un nuevo % aplica a sus próximas liquidaciones; las facturas ya emitidas no cambian.
                </p>
            @endif
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('tipos-cliente.update', $tipoCliente) }}">
                    @method('PUT')
                    @include('tipos-cliente._form')
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
