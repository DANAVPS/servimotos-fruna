@php
    $cliente = new App\Models\Cliente(['tipo_cliente_id' => $tipoPorDefecto->id]);
@endphp

<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">Nuevo cliente</h2></x-slot>
    <div class="py-6">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('clientes.store') }}" class="space-y-6">
                @csrf

                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-sm font-semibold text-gray-800">Datos del cliente</h3>
                    @include('clientes._form')
                </div>

                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-800">Moto</h3>
                    <p class="mb-4 text-xs text-gray-500">Todo cliente se registra con al menos una moto.</p>

                    @include('clientes._moto-campos', ['prefijo' => 'moto', 'clave' => 'moto', 'moto' => null, 'obligatoria' => true])
                </div>

                <p class="text-xs text-gray-500">El tratamiento de estos datos se rige por la Ley 1581 de 2012 (Habeas Data).</p>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('clientes.index') }}" class="rounded-md border bg-white px-4 py-2 text-sm">Cancelar</a>
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Registrar cliente</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
