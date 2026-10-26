@php
    $estados = [
        'activo' => 'Activo — cliente vigente (al guardar cuenta como visita de hoy)',
        'hibernando' => 'Hibernando — sin visitas recientes',
        'archivado' => 'Archivado — se oculta del listado y de las búsquedas',
    ];
    $sinMotos = $cliente->motos->isEmpty();
    $estadoInicial = old('estado_cliente', $cliente->estado_cliente);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" onclick="history.length > 1 ? history.back() : location.assign('{{ route('clientes.index') }}')"
                        class="text-sm text-gray-500 hover:text-gray-800">← Volver</button>
                <h2 class="text-xl font-semibold text-gray-800">Editar cliente</h2>
            </div>
            <a href="{{ route('clientes.show', $cliente) }}"
               class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                Ver historial de órdenes
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">

            @if($cliente->estado_cliente === 'archivado')
                <div class="mb-6 rounded-md border border-gray-300 bg-gray-50 p-4 text-sm text-gray-700">
                    Este cliente está <strong>archivado</strong>. Cambia el estado a Activo para que vuelva a aparecer en el listado.
                </div>
            @endif

            <form method="POST" action="{{ route('clientes.update', $cliente) }}" class="space-y-6"
                  x-data="formularioConPin()" @submit.prevent="pedirPin()">
                @csrf
                @method('PUT')
                @include('partials.pin-modal', ['titulo' => 'Guardar cambios del cliente'])

                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="mb-4 text-sm font-semibold text-gray-800">Datos del cliente</h3>
                    @include('clientes._form')
                </div>

                <div class="rounded-lg bg-white p-6 shadow-sm" x-data="{ estado: @js($estadoInicial), original: @js($cliente->estado_cliente) }">
                    <label for="estado_cliente" class="block text-sm font-semibold text-gray-800">Estado</label>
                    <p class="mb-3 text-xs text-gray-500">
                        El sistema lo actualiza solo según la última visita (activo 0–5 meses, hibernando 5–10, archivado +10).
                        Aquí puedes cambiarlo a mano.
                    </p>
                    <select name="estado_cliente" id="estado_cliente" x-model="estado"
                            class="block w-full rounded-md border-gray-300 shadow-sm sm:w-auto">
                        @foreach($estados as $valor => $etiqueta)
                            <option value="{{ $valor }}" @selected($estadoInicial === $valor)>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                    <p x-show="estado !== original" x-cloak class="mt-2 rounded-md bg-amber-50 p-2 text-sm text-amber-800">
                        Al guardar, el estado pasará de <strong x-text="original"></strong> a <strong x-text="estado"></strong>.
                        <span x-show="estado === 'archivado'">El cliente dejará de aparecer en el listado por defecto.</span>
                    </p>
                    @error('estado_cliente') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="rounded-lg bg-white p-6 shadow-sm" x-data="{ agregar: @js($sinMotos || filled(old('nueva_moto.placa'))) }">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-800">{{ $cliente->motos->count() === 1 ? 'Moto' : 'Motos' }}</h3>
                        @unless($sinMotos)
                            <button type="button" x-show="! agregar" @click="agregar = true"
                                    class="text-sm font-medium text-indigo-600 hover:text-indigo-800">+ Agregar otra moto</button>
                        @endunless
                    </div>

                    @error('motos') <p class="mb-3 text-sm text-red-600">{{ $message }}</p> @enderror

                    <div class="space-y-4">
                        @foreach($cliente->motos as $moto)
                            @include('clientes._moto-campos', [
                                'prefijo' => "motos[{$moto->id}]",
                                'clave' => "motos.{$moto->id}",
                                'moto' => $moto,
                                'obligatoria' => true,
                            ])
                        @endforeach

                        <div x-show="agregar" x-cloak>
                            @if($sinMotos)
                                <p class="mb-3 rounded-md bg-amber-50 p-2 text-sm text-amber-800">Este cliente no tiene moto registrada. Agrégala aquí.</p>
                            @else
                                <p class="mb-2 text-xs font-semibold uppercase text-gray-500">Moto nueva</p>
                            @endif
                            @include('clientes._moto-campos', [
                                'prefijo' => 'nueva_moto',
                                'clave' => 'nueva_moto',
                                'moto' => null,
                                'obligatoria' => false,
                            ])
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('clientes.index') }}" class="rounded-md border bg-white px-4 py-2 text-sm">Cancelar</a>
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
