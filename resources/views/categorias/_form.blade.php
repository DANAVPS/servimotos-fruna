@csrf
<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div class="sm:col-span-2">
        <label for="nombre" class="block text-sm font-medium text-gray-700">Nombre</label>
        <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $categoria->nombre) }}" required maxlength="100"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        @error('nombre') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="orden" class="block text-sm font-medium text-gray-700">Orden</label>
        <input type="number" name="orden" id="orden" min="0" max="999" value="{{ old('orden', $categoria->orden) }}" required
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        @error('orden') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div class="sm:col-span-3">
        <input type="hidden" name="activo" value="0">
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="activo" value="1" @checked(old('activo', $categoria->activo)) class="rounded border-gray-300">
            Activa (se ofrece al crear o editar repuestos)
        </label>
    </div>
</div>

<div class="mt-6 flex justify-end gap-3">
    <a href="{{ route('categorias.index') }}" class="rounded-md border px-4 py-2 text-sm">Cancelar</a>
    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Guardar</button>
</div>
