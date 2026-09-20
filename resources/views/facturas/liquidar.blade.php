<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">
            Liquidar Orden #{{ $orden->id }} — {{ $orden->moto->placa }}
        </h2>
    </x-slot>

    <div class="py-6" x-data="liquidacion()">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">

            @if($errors->any())
                <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('facturas.generar', $orden) }}" class="rounded-lg bg-white p-6 shadow-sm">
                @csrf

                <template x-for="(item, index) in items" :key="index">
                    <div class="mb-3 grid grid-cols-12 gap-2 items-end">
                        <div class="col-span-5">
                            <label class="block text-xs font-medium text-gray-700">Repuesto</label>
                            <select :name="`items[${index}][repuesto_id]`" x-model="item.repuesto_id"
                                    @change="actualizarPrecio(index)"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                <option value="">-- Seleccionar --</option>
                                @foreach($repuestos as $repuesto)
                                    <option value="{{ $repuesto->id }}" data-precio="{{ $repuesto->precio_venta }}" data-stock="{{ $repuesto->stock_actual }}">
                                        {{ $repuesto->nombre }} (stock: {{ $repuesto->stock_actual }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-gray-700">Cantidad</label>
                            <input type="number" min="1" :name="`items[${index}][cantidad]`" x-model.number="item.cantidad"
                                   @input="calcularTotales()"
                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-gray-700">Mano de obra</label>
                            <input type="number" min="0" :name="`items[${index}][mano_obra]`" x-model.number="item.mano_obra"
                                   @input="calcularTotales()"
                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div class="col-span-2 text-sm text-gray-600">
                            $<span x-text="formatear(item.subtotal)"></span>
                        </div>
                        <div class="col-span-1">
                            <button type="button" @click="quitar(index)" class="text-red-600 text-sm">✕</button>
                        </div>
                    </div>
                </template>

                <button type="button" @click="agregar()" class="mb-4 text-sm font-medium text-indigo-600">
                    + Agregar repuesto
                </button>

                <div class="mt-6 border-t pt-4 text-right text-sm">
                    <p class="text-gray-600">Subtotal: $<span x-text="formatear(totales.subtotal)"></span></p>
                    <p class="text-gray-600">IVA (19%): $<span x-text="formatear(totales.iva)"></span></p>
                    <p class="text-lg font-bold text-gray-900">Total: $<span x-text="formatear(totales.total)"></span></p>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <a href="{{ route('ordenes.show', $orden) }}" class="rounded-md border px-4 py-2 text-sm">Cancelar</a>
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">
                        Generar Factura
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        function liquidacion() {
            return {
                items: [{ repuesto_id: '', cantidad: 1, mano_obra: 0, subtotal: 0 }],
                totales: { subtotal: 0, iva: 0, total: 0 },

                agregar() {
                    this.items.push({ repuesto_id: '', cantidad: 1, mano_obra: 0, subtotal: 0 });
                },

                quitar(index) {
                    this.items.splice(index, 1);
                    this.calcularTotales();
                },

                actualizarPrecio(index) {
                    const select = document.querySelectorAll('select')[index];
                    const opcion = select.options[select.selectedIndex];
                    const precio = parseFloat(opcion?.dataset.precio ?? 0);
                    this.items[index].precio = precio;
                    this.calcularTotales();
                },

                calcularTotales() {
                    let subtotal = 0;

                    this.items.forEach((item, index) => {
                        const select = document.querySelectorAll('select')[index];
                        const opcion = select?.options[select.selectedIndex];
                        const precio = parseFloat(opcion?.dataset.precio ?? 0);
                        item.subtotal = (precio * (item.cantidad || 0)) + parseFloat(item.mano_obra || 0);
                        subtotal += item.subtotal;
                    });

                    const iva = subtotal * 0.19;
                    this.totales = {
                        subtotal: subtotal,
                        iva: iva,
                        total: subtotal + iva,
                    };
                },

                formatear(valor) {
                    return new Intl.NumberFormat('es-CO').format(Math.round(valor || 0));
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
