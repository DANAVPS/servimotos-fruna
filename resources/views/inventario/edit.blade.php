<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">Editar repuesto</h2></x-slot>
    <div class="py-6">
        <div class="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('inventario.update', $repuesto) }}">
                    @method('PUT')
                    @include('inventario._form')
                </form>
            </div>

            @can('delete', $repuesto)
                {{-- Eliminación con doble verificación: casilla + escribir ELIMINAR.
                     El backend (EliminarRepuestoRequest) vuelve a exigir ambas. --}}
                <div x-data="{
                        abierto: @js($errors->has('confirmo') || $errors->has('confirmacion')),
                        confirmo: false,
                        texto: '',
                        get listo() { return this.confirmo && this.texto === 'ELIMINAR' },
                        cerrar() { this.abierto = false; this.confirmo = false; this.texto = '' },
                     }"
                     @keydown.escape.window="cerrar()"
                     class="rounded-lg border border-red-200 bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold text-red-800">Eliminar repuesto</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Deja de aparecer en el inventario y ya no se puede vender. Las facturas anteriores lo siguen mostrando.
                    </p>
                    <button type="button" @click="abierto = true"
                            class="mt-4 rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500">
                        Eliminar
                    </button>

                    <div x-show="abierto" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
                         role="dialog" aria-modal="true" aria-labelledby="titulo-eliminar">
                        <div class="absolute inset-0 bg-gray-900/50" @click="cerrar()"></div>

                        <form method="POST" action="{{ route('inventario.destroy', $repuesto) }}"
                              class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                            @csrf
                            @method('DELETE')

                            <h3 id="titulo-eliminar" class="text-lg font-semibold text-gray-900">¿Eliminar este repuesto?</h3>
                            <p class="mt-2 rounded-md bg-gray-50 p-3 text-sm text-gray-800">
                                <span class="font-semibold">{{ $repuesto->nombre }}</span>
                                @if($repuesto->codigo_sku) <span class="text-gray-500">({{ $repuesto->codigo_sku }})</span> @endif
                                <br><span class="text-gray-500">Stock actual: {{ $repuesto->stock_actual }}</span>
                            </p>

                            <label class="mt-4 flex items-start gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="confirmo" value="1" x-model="confirmo" class="mt-0.5 rounded border-gray-300">
                                Entiendo que el repuesto dejará de estar disponible en el inventario.
                            </label>
                            @error('confirmo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                            <label for="confirmacion" class="mt-4 block text-sm text-gray-700">
                                Escribe <span class="font-mono font-semibold">ELIMINAR</span> para confirmar:
                            </label>
                            <input type="text" name="confirmacion" id="confirmacion" x-model="texto" autocomplete="off"
                                   x-effect="abierto && $nextTick(() => $el.focus())"
                                   class="mt-1 block w-full rounded-md border-gray-300 font-mono shadow-sm">
                            @error('confirmacion') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                            <div class="mt-6 flex justify-end gap-3">
                                <button type="button" @click="cerrar()" class="rounded-md border px-4 py-2 text-sm">Cancelar</button>
                                <button type="submit" :disabled="! listo"
                                        class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500 disabled:cursor-not-allowed disabled:opacity-40">
                                    Eliminar definitivamente
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endcan
        </div>
    </div>
</x-app-layout>
