@csrf
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <label class="block text-sm font-medium text-gray-700">Nombre</label>
        <input type="text" name="nombre" value="{{ old('nombre', $repuesto->nombre ?? '') }}"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
        @error('nombre') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700">Marca</label>
        <input type="text" name="marca" value="{{ old('marca', $repuesto->marca ?? '') }}"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
    </div>
    <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-gray-700">Modelo compatible</label>
        <input type="text" name="modelo_compatible" value="{{ old('modelo_compatible', $repuesto->modelo_compatible ?? '') }}"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700">Precio costo (COP)</label>
        <input type="number" step="1" name="precio_costo" value="{{ old('precio_costo', $repuesto->precio_costo ?? 0) }}"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
        @error('precio_costo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700">Precio venta (COP)</label>
        <input type="number" step="1" name="precio_venta" value="{{ old('precio_venta', $repuesto->precio_venta ?? 0) }}"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
        @error('precio_venta') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700">Stock actual</label>
        <input type="number" name="stock_actual" value="{{ old('stock_actual', $repuesto->stock_actual ?? 0) }}"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700">Stock mínimo (umbral de alerta)</label>
        <input type="number" name="stock_minimo" value="{{ old('stock_minimo', $repuesto->stock_minimo ?? 0) }}"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
    </div>
</div>

<div class="mt-6 flex justify-end gap-3">
    <a href="{{ route('inventario.index') }}" class="rounded-md border px-4 py-2 text-sm">Cancelar</a>
    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Guardar</button>
</div>
