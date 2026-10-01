@csrf
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="nombre" class="block text-sm font-medium text-gray-700">Nombre</label>
        <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $distribuidor->nombre) }}" required maxlength="150"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        @error('nombre') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="contacto" class="block text-sm font-medium text-gray-700">Persona de contacto</label>
        <input type="text" name="contacto" id="contacto" value="{{ old('contacto', $distribuidor->contacto) }}" maxlength="150"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        @error('contacto') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="telefono_whatsapp" class="block text-sm font-medium text-gray-700">WhatsApp</label>
        <input type="tel" name="telefono_whatsapp" id="telefono_whatsapp" value="{{ old('telefono_whatsapp', $distribuidor->telefono_whatsapp) }}"
               placeholder="300 123 4567" maxlength="20"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        @error('telefono_whatsapp') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div class="sm:col-span-2">
        <label for="email" class="block text-sm font-medium text-gray-700">Correo</label>
        <input type="email" name="email" id="email" value="{{ old('email', $distribuidor->email) }}"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div class="sm:col-span-2">
        <label for="notas" class="block text-sm font-medium text-gray-700">Notas</label>
        <textarea name="notas" id="notas" rows="3" placeholder="Horarios, condiciones de pago, días de despacho…"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('notas', $distribuidor->notas) }}</textarea>
        @error('notas') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div class="sm:col-span-2">
        <input type="hidden" name="activo" value="0">
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="activo" value="1" @checked(old('activo', $distribuidor->activo)) class="rounded border-gray-300">
            Activo (se le asignan pedidos de stock bajo)
        </label>
    </div>
</div>

<div class="mt-6 flex justify-end gap-3">
    <a href="{{ route('distribuidores.index') }}" class="rounded-md border px-4 py-2 text-sm">Cancelar</a>
    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Guardar</button>
</div>
