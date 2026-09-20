<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Tablero de Órdenes de Servicio</h2>
    </x-slot>

    <div class="py-6" x-data="kanbanBoard()">
        <div class="mx-auto max-w-full px-4 sm:px-6 lg:px-8">

            <div x-show="mensajeError" x-transition
                 class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700" x-text="mensajeError"></div>

            <div class="flex gap-4 overflow-x-auto pb-4">
                @foreach($columnas as $estado)
                    <div class="w-72 flex-shrink-0 rounded-lg bg-gray-100 p-3">
                        <h3 class="mb-3 flex items-center justify-between text-sm font-semibold text-gray-700">
                            {{ ucwords(str_replace('_', ' ', $estado)) }}
                            <span class="rounded-full bg-gray-300 px-2 py-0.5 text-xs">
                                {{ ($ordenes[$estado] ?? collect())->count() }}
                            </span>
                        </h3>

                        <div class="kanban-column min-h-[100px] space-y-2"
                             data-estado="{{ $estado }}"
                             {{-- 'pagada' no es un destino válido de drag & drop --}}
                             data-draggable="{{ $estado === 'pagada' ? 'false' : 'true' }}">
                            @foreach($ordenes[$estado] ?? [] as $orden)
                                <div class="kanban-card cursor-move rounded-md border border-gray-200 bg-white p-3 shadow-sm"
                                     data-id="{{ $orden->id }}">
                                    <a href="{{ route('ordenes.show', $orden) }}" class="block">
                                        <p class="text-sm font-semibold text-gray-900">{{ $orden->moto->placa }}</p>
                                        <p class="text-xs text-gray-500">{{ $orden->cliente->nombre }}</p>
                                        <p class="text-xs text-gray-400">{{ $orden->moto->marca }} {{ $orden->moto->modelo }}</p>
                                        @if($orden->mecanico)
                                            <p class="mt-1 text-xs text-indigo-600">{{ $orden->mecanico->nombre }}</p>
                                        @endif
                                    </a>

                                    @if($estado === 'listo_para_reclamar' && (auth()->user()->esAdministradora() || auth()->user()->esSuperAdmin()))
                                        <button
                                            @click="marcarPagada({{ $orden->id }})"
                                            class="mt-2 w-full rounded bg-green-600 px-2 py-1 text-xs font-semibold text-white hover:bg-green-500">
                                            Marcar como pagada
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
    <script>
        function kanbanBoard() {
            return {
                mensajeError: null,

                init() {
                    document.querySelectorAll('.kanban-column').forEach((columna) => {
                        new Sortable(columna, {
                            group: 'ordenes',
                            animation: 150,
                            disabled: columna.dataset.draggable === 'false',
                            onAdd: (evento) => this.moverOrden(evento, columna),
                        });
                    });
                },

                async moverOrden(evento, columnaDestino) {
                    const ordenId = evento.item.dataset.id;
                    const nuevoEstado = columnaDestino.dataset.estado;

                    try {
                        const respuesta = await fetch(`/ordenes/${ordenId}/estado`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({ estado: nuevoEstado }),
                        });

                        const datos = await respuesta.json();

                        if (!respuesta.ok) {
                            this.mensajeError = datos.message;
                            evento.from.appendChild(evento.item); // revertir visualmente
                            setTimeout(() => (this.mensajeError = null), 4000);
                        }
                    } catch (error) {
                        this.mensajeError = 'Error de conexión al actualizar la orden.';
                        evento.from.appendChild(evento.item);
                    }
                },

                async marcarPagada(ordenId) {
                    if (!confirm('¿Confirmas que esta orden fue pagada?')) return;

                    try {
                        const respuesta = await fetch(`/ordenes/${ordenId}/pagar`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({ metodo_pago: 'efectivo' }),
                        });

                        const datos = await respuesta.json();

                        if (respuesta.ok) {
                            window.location.reload();
                        } else {
                            this.mensajeError = datos.message ?? 'No tienes permiso para esta acción.';
                        }
                    } catch (error) {
                        this.mensajeError = 'Error de conexión.';
                    }
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
