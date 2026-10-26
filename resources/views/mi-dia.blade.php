{{--
    "Mi día" del mecánico: solo lectura, solo sus órdenes activas.
    Regla: aquí no hay dinero (precio, total, descuento, método de pago) ni
    datos de contacto del cliente. El controlador solo envía arreglos con los
    campos permitidos.
--}}
@php
    $colorSemaforo = ['verde' => 'bg-green-500', 'amarillo' => 'bg-yellow-400', 'rojo' => 'bg-red-500'];
    $bordeSemaforo = ['verde' => 'border-l-green-500', 'amarillo' => 'border-l-yellow-400', 'rojo' => 'border-l-red-500'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold text-gray-800">Mi día</h2>
            <a href="{{ route('ordenes.kanban') }}"
               class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                Ir al tablero
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">

            @unless($vinculado)
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800" role="alert">
                    <p class="font-semibold">Hola {{ $nombre }}.</p>
                    <p class="mt-1">Tu usuario no está vinculado a un mecánico, contacta a la administradora.</p>
                </div>
            @else
                {{-- Resumen --}}
                <section class="rounded-lg bg-white p-5 shadow-sm">
                    <p class="text-lg font-semibold text-gray-900">
                        Hola {{ $nombre }}.
                        Tienes {{ $totalActivas }} {{ $totalActivas === 1 ? 'orden activa' : 'órdenes activas' }}.
                    </p>
                    <p class="mt-1 text-sm text-gray-600">
                        Esta semana terminaste <strong>{{ $terminadasSemana }}</strong> {{ $terminadasSemana === 1 ? 'moto' : 'motos' }}.
                    </p>

                    <dl class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-5">
                        @foreach($estados as $estado)
                            <div class="rounded-md bg-gray-50 px-3 py-2">
                                <dt class="text-xs text-gray-500">{{ $etiquetas[$estado] }}</dt>
                                <dd class="text-xl font-bold text-gray-900">{{ $porEstado[$estado]->count() }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>

                {{-- Lo que se está demorando, primero --}}
                @if($enRojo->isNotEmpty())
                    <section class="rounded-lg border border-red-200 bg-red-50 p-4" role="alert" aria-labelledby="titulo-demoradas">
                        <h3 id="titulo-demoradas" class="flex items-center gap-2 text-sm font-semibold text-red-800">
                            <span class="h-2.5 w-2.5 rounded-full bg-red-500"></span>
                            {{ $enRojo->count() }} {{ $enRojo->count() === 1 ? 'moto lleva' : 'motos llevan' }} más de {{ $umbralRojo }} días en el taller
                        </h3>
                        <ul class="mt-2 space-y-1 text-sm text-red-900">
                            @foreach($enRojo as $orden)
                                <li>
                                    <span class="font-mono font-semibold">{{ $orden['placa'] }}</span>
                                    · {{ $orden['moto'] }}
                                    · {{ $etiquetas[$orden['estado']] }}
                                    · <strong>{{ $orden['dias'] }} días</strong>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                {{-- Órdenes agrupadas por estado (dentro de cada grupo, la más antigua arriba) --}}
                @if($totalActivas === 0)
                    <p class="rounded-lg bg-white p-6 text-center text-sm text-gray-500 shadow-sm">
                        No tienes órdenes asignadas en este momento.
                    </p>
                @endif

                @foreach($estados as $estado)
                    @continue($porEstado[$estado]->isEmpty())

                    <section aria-labelledby="grupo-{{ $estado }}">
                        <h3 id="grupo-{{ $estado }}" class="mb-2 flex items-center gap-2 text-sm font-semibold text-gray-700">
                            {{ $etiquetas[$estado] }}
                            <span class="rounded-full bg-gray-200 px-2 py-0.5 text-xs">{{ $porEstado[$estado]->count() }}</span>
                        </h3>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach($porEstado[$estado] as $orden)
                                <article class="rounded-md border border-l-4 border-gray-200 bg-white p-4 shadow-sm {{ $bordeSemaforo[$orden['semaforo']] }}">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <p class="font-mono text-base font-semibold text-gray-900">{{ $orden['placa'] }}</p>
                                            <p class="text-sm text-gray-600">
                                                {{ $orden['moto'] }}@if($orden['cliente']) · Moto de {{ $orden['cliente'] }}@endif
                                            </p>
                                        </div>
                                        <span class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium
                                                     {{ $orden['semaforo'] === 'rojo' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-700' }}"
                                              title="Días en el taller desde el ingreso">
                                            <span class="h-2.5 w-2.5 rounded-full {{ $colorSemaforo[$orden['semaforo']] }}"></span>
                                            {{ $orden['dias'] }} {{ $orden['dias'] === 1 ? 'día' : 'días' }}
                                        </span>
                                    </div>

                                    <p class="mt-2 text-sm text-gray-800">{{ $orden['falla'] ?? 'Sin descripción de la falla.' }}</p>

                                    <p class="mt-3 flex flex-wrap items-center justify-between gap-1 text-xs text-gray-500">
                                        <span class="flex flex-wrap gap-1">
                                            <span class="rounded bg-indigo-50 px-1.5 py-0.5 text-indigo-700">{{ $etiquetas[$orden['estado']] }}</span>
                                            @if($orden['estado'] === 'terminada')
                                                <span class="rounded px-1.5 py-0.5 {{ $orden['trabajo_registrado'] ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-800' }}">
                                                    {{ $orden['trabajo_registrado'] ? 'Trabajo registrado' : 'Falta registrar el trabajo' }}
                                                </span>
                                            @endif
                                        </span>
                                        <span>Ingresó {{ $orden['ingreso'] }}</span>
                                    </p>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endforeach

                <p class="text-center text-xs text-gray-400">
                    Esta pantalla es de consulta. Para mover una orden, usa el
                    <a href="{{ route('ordenes.kanban') }}" class="text-indigo-600 underline">tablero</a>.
                    Recarga la página para ver cambios.
                </p>
            @endunless
        </div>
    </div>
</x-app-layout>
