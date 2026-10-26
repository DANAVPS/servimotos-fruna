@csrf
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2"
     x-data="{ tipo: @js(old('tipo', $mecanico->tipo ?? 'titular')) }">
    <div class="sm:col-span-2">
        <label for="nombre" class="block text-sm font-medium text-gray-700">Nombre</label>
        <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $mecanico->nombre) }}" required maxlength="255"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        @error('nombre') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="telefono" class="block text-sm font-medium text-gray-700">Teléfono <span class="text-gray-400">(opcional)</span></label>
        <input type="tel" name="telefono" id="telefono" value="{{ old('telefono', $mecanico->telefono) }}" placeholder="300 123 4567"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        @error('telefono') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="tipo" class="block text-sm font-medium text-gray-700">Tipo</label>
        <select name="tipo" id="tipo" x-model="tipo" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            @foreach(App\Models\Mecanico::TIPOS as $valor => $etiqueta)
                <option value="{{ $valor }}">{{ $etiqueta }}</option>
            @endforeach
        </select>
        @error('tipo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    {{-- Solo para ayudantes: el servidor ignora este campo si el tipo es titular. --}}
    <div class="sm:col-span-2" x-show="tipo === 'ayudante'" x-cloak>
        <label for="mecanico_principal_id" class="block text-sm font-medium text-gray-700">Titular con el que trabaja <span class="text-gray-400">(opcional)</span></label>
        <select name="mecanico_principal_id" id="mecanico_principal_id" :disabled="tipo !== 'ayudante'"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            <option value="">Sin titular asignado</option>
            @foreach($titulares as $titular)
                <option value="{{ $titular->id }}" @selected((int) old('mecanico_principal_id', $mecanico->mecanico_principal_id) === $titular->id)>
                    {{ $titular->nombre }}
                </option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-gray-500">Solo aparecen titulares activos del taller.</p>
        @error('mecanico_principal_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2">
        <label for="user_id" class="block text-sm font-medium text-gray-700">Usuario del sistema <span class="text-gray-400">(opcional)</span></label>
        <select name="user_id" id="user_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            <option value="">Sin usuario (no entra a "Mi día")</option>
            @foreach($usuarios as $usuario)
                <option value="{{ $usuario->id }}" @selected((int) old('user_id', $mecanico->user_id) === $usuario->id)>
                    {{ $usuario->name }} · {{ $usuario->email }}
                </option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-gray-500">Cuenta con rol mecánico con la que esta persona ve sus órdenes en "Mi día".</p>
        @error('user_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2">
        <input type="hidden" name="activo" value="0">
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="activo" value="1" @checked(old('activo', $mecanico->activo ?? true)) class="rounded border-gray-300">
            Activo (aparece al asignar órdenes)
        </label>
    </div>
</div>

<div class="mt-6 flex justify-end gap-3">
    <a href="{{ route('mecanicos.index') }}" class="rounded-md border px-4 py-2 text-sm">Cancelar</a>
    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Guardar</button>
</div>
