@csrf
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <label class="block text-sm font-medium text-gray-700">Nombre completo</label>
        <input type="text" name="nombre" value="{{ old('nombre', $cliente->nombre ?? '') }}"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
        @error('nombre') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700">Teléfono (WhatsApp)</label>
        <input type="text" name="telefono" value="{{ old('telefono', $cliente->telefono ?? '') }}"
               placeholder="+573001234567"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
        @error('telefono') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-gray-700">Correo (opcional)</label>
        <input type="email" name="correo" value="{{ old('correo', $cliente->correo ?? '') }}"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        @error('correo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
</div>

<p class="mt-4 text-xs text-gray-500">
    El tratamiento de estos datos se rige por la Ley 1581 de 2012 (Habeas Data).
</p>

<div class="mt-6 flex justify-end gap-3">
    <a href="{{ route('clientes.index') }}" class="rounded-md border px-4 py-2 text-sm">Cancelar</a>
    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Guardar</button>
</div>
