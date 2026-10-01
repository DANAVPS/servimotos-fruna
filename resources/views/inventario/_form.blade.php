@php
    $vinculos = old('distribuidores', $repuesto->exists
        ? $repuesto->distribuidores->map(fn ($d) => [
            'distribuidor_id' => $d->id,
            'codigo_proveedor' => $d->pivot->codigo_proveedor,
            'precio_costo_proveedor' => $d->pivot->precio_costo_proveedor !== null ? (float) $d->pivot->precio_costo_proveedor : null,
            'tiempo_entrega_dias' => $d->pivot->tiempo_entrega_dias,
        ])->values()->all()
        : []);

    $compatibilidades = old('compatibilidades', $repuesto->exists
        ? $repuesto->compatibilidades->pluck('moto_referencia')->implode("\n")
        : '');
@endphp

@csrf
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="nombre" class="block text-sm font-medium text-gray-700">Nombre</label>
        <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $repuesto->nombre) }}"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
        @error('nombre') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="categoria_repuesto_id" class="block text-sm font-medium text-gray-700">Categoría</label>
        <select name="categoria_repuesto_id" id="categoria_repuesto_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            <option value="">Sin categoría</option>
            @foreach($categorias as $categoria)
                <option value="{{ $categoria->id }}" @selected((string) old('categoria_repuesto_id', $repuesto->categoria_repuesto_id) === (string) $categoria->id)>
                    {{ $categoria->nombre }}{{ $categoria->activo ? '' : ' (inactiva)' }}
                </option>
            @endforeach
        </select>
        @error('categoria_repuesto_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="marca" class="block text-sm font-medium text-gray-700">Marca del producto</label>
        <input type="text" name="marca" id="marca" value="{{ old('marca', $repuesto->marca) }}"
               placeholder="Ej. Kontrol, NGK, Castrol"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        @error('marca') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="codigo_sku" class="block text-sm font-medium text-gray-700">SKU interno</label>
        <input type="text" name="codigo_sku" id="codigo_sku" value="{{ old('codigo_sku', $repuesto->codigo_sku) }}"
               placeholder="Ej. REP-FIL-001"
               class="mt-1 block w-full rounded-md border-gray-300 uppercase shadow-sm">
        @error('codigo_sku') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="referencia_proveedor" class="block text-sm font-medium text-gray-700">Referencia del proveedor</label>
        <input type="text" name="referencia_proveedor" id="referencia_proveedor"
               value="{{ old('referencia_proveedor', $repuesto->referencia_proveedor) }}"
               placeholder="Código con el que se pide al distribuidor"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        @error('referencia_proveedor') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2">
        <label for="compatibilidades" class="block text-sm font-medium text-gray-700">Motos compatibles</label>
        <textarea name="compatibilidades" id="compatibilidades" rows="3"
                  placeholder="Una por línea o separadas por / (ej. Pulsar NS 200 / Duke 200)"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ $compatibilidades }}</textarea>
        @error('compatibilidades') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="precio_costo" class="block text-sm font-medium text-gray-700">Precio costo (COP)</label>
        <input type="number" step="1" min="0" name="precio_costo" id="precio_costo"
               value="{{ old('precio_costo', $repuesto->precio_costo !== null ? (int) $repuesto->precio_costo : 0) }}"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
        @error('precio_costo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="precio_venta" class="block text-sm font-medium text-gray-700">Precio venta (COP, IVA incluido)</label>
        <input type="number" step="1" min="0" name="precio_venta" id="precio_venta"
               value="{{ old('precio_venta', $repuesto->precio_venta !== null ? (int) $repuesto->precio_venta : 0) }}"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
        @error('precio_venta') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="stock_actual" class="block text-sm font-medium text-gray-700">Stock actual</label>
        <input type="number" min="0" name="stock_actual" id="stock_actual" value="{{ old('stock_actual', $repuesto->stock_actual ?? 0) }}"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
        @error('stock_actual') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="stock_minimo" class="block text-sm font-medium text-gray-700">Stock mínimo (umbral de alerta)</label>
        <input type="number" min="0" name="stock_minimo" id="stock_minimo" value="{{ old('stock_minimo', $repuesto->stock_minimo ?? 1) }}"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
        @error('stock_minimo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div class="sm:col-span-2">
        <label for="ubicacion_estante" class="block text-sm font-medium text-gray-700">Ubicación en bodega</label>
        <input type="text" name="ubicacion_estante" id="ubicacion_estante"
               value="{{ old('ubicacion_estante', $repuesto->ubicacion_estante) }}"
               placeholder="Ej. Estante A - Fila 2"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        @error('ubicacion_estante') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
</div>

{{-- Distribuidores que surten este repuesto --}}
<div id="distribuidores" class="mt-6 border-t pt-6"
     x-data="{
        vinculos: @js($vinculos),
        agregar() { this.vinculos.push({ distribuidor_id: '', codigo_proveedor: '', precio_costo_proveedor: '', tiempo_entrega_dias: '' }) },
     }">
    <input type="hidden" name="distribuidores_enviados" value="1">

    <div class="mb-3 flex items-center justify-between">
        <div>
            <h3 class="text-sm font-semibold text-gray-800">Distribuidores</h3>
            <p class="text-xs text-gray-500">Cuando haya stock bajo se pide por defecto al que entrega más rápido.</p>
        </div>
        @if($distribuidores->isNotEmpty())
            <button type="button" @click="agregar()" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                + Agregar distribuidor
            </button>
        @endif
    </div>

    @if($distribuidores->isEmpty())
        <p class="text-sm text-gray-500">
            Aún no hay distribuidores registrados.
            <a href="{{ route('distribuidores.create') }}" class="text-indigo-600 hover:underline">Crear uno</a>.
        </p>
    @endif

    <template x-if="vinculos.length === 0 && {{ $distribuidores->isNotEmpty() ? 'true' : 'false' }}">
        <p class="text-sm text-gray-500">Este repuesto no tiene distribuidor asignado.</p>
    </template>

    <div class="space-y-3">
        <template x-for="(vinculo, i) in vinculos" :key="i">
            <div class="grid grid-cols-2 gap-2 rounded-md border border-gray-200 p-3 sm:grid-cols-12 sm:items-end">
                <div class="col-span-2 sm:col-span-4">
                    <label class="block text-xs text-gray-600">Distribuidor</label>
                    <select :name="`distribuidores[${i}][distribuidor_id]`" x-model="vinculo.distribuidor_id" required
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">Selecciona…</option>
                        @foreach($distribuidores as $distribuidor)
                            <option value="{{ $distribuidor->id }}">{{ $distribuidor->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-3">
                    <label class="block text-xs text-gray-600">Código de este distribuidor</label>
                    <input type="text" :name="`distribuidores[${i}][codigo_proveedor]`" x-model="vinculo.codigo_proveedor"
                           placeholder="{{ $repuesto->referencia_proveedor ?: 'Con el que él la identifica' }}"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs text-gray-600">Costo proveedor</label>
                    <input type="number" min="0" step="1" :name="`distribuidores[${i}][precio_costo_proveedor]`" x-model="vinculo.precio_costo_proveedor"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs text-gray-600">Entrega (días)</label>
                    <input type="number" min="0" max="365" :name="`distribuidores[${i}][tiempo_entrega_dias]`" x-model="vinculo.tiempo_entrega_dias"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                </div>
                <div class="text-right sm:col-span-1">
                    <button type="button" @click="vinculos.splice(i, 1)" class="text-sm text-red-600 hover:text-red-800">Quitar</button>
                </div>
            </div>
        </template>
    </div>

    @if($errors->has('distribuidores*'))
        <div class="mt-2 space-y-1">
            @foreach($errors->get('distribuidores*') as $mensajes)
                @foreach($mensajes as $mensaje)
                    <p class="text-sm text-red-600">{{ $mensaje }}</p>
                @endforeach
            @endforeach
        </div>
    @endif
</div>

<div class="mt-6 flex justify-end gap-3">
    <a href="{{ route('inventario.index') }}" class="rounded-md border px-4 py-2 text-sm">Cancelar</a>
    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Guardar</button>
</div>
