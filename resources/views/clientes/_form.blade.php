{{-- Datos del cliente, compartidos por crear y editar. Requiere $cliente y $tiposCliente. --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="nombre" class="block text-sm font-medium text-gray-700">Nombre completo o razón social</label>
        <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $cliente->nombre) }}" required maxlength="255"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        @error('nombre') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="telefono" class="block text-sm font-medium text-gray-700">Celular (WhatsApp)</label>
        <input type="tel" name="telefono" id="telefono" value="{{ old('telefono', $cliente->telefono) }}" required
               placeholder="300 123 4567"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        @error('telefono') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="cedula_nit" class="block text-sm font-medium text-gray-700">Cédula o NIT <span class="font-normal text-gray-400">(opcional)</span></label>
        <input type="text" name="cedula_nit" id="cedula_nit" value="{{ old('cedula_nit', $cliente->cedula_nit) }}"
               placeholder="1020304050 o 900123456-1"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        @error('cedula_nit') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="correo" class="block text-sm font-medium text-gray-700">Correo <span class="font-normal text-gray-400">(opcional)</span></label>
        <input type="email" name="correo" id="correo" value="{{ old('correo', $cliente->correo) }}"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        @error('correo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="tipo_cliente_id" class="block text-sm font-medium text-gray-700">Tipo de cliente</label>
        <select name="tipo_cliente_id" id="tipo_cliente_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            @foreach($tiposCliente as $tipo)
                <option value="{{ $tipo->id }}" @selected((string) old('tipo_cliente_id', $cliente->tipo_cliente_id) === (string) $tipo->id)>
                    {{ $tipo->nombre }}
                    — {{ $tipo->tieneDescuento() ? rtrim(rtrim($tipo->descuento_repuestos_pct, '0'), '.') . '% de descuento en repuestos' : 'precio de lista' }}
                    {{ $tipo->activo ? '' : '(inactivo)' }}
                </option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-gray-500">El descuento se aplica solo a repuestos al liquidar; la mano de obra no cambia.</p>
        @error('tipo_cliente_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
</div>
