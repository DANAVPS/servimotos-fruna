{{--
    Campos de una moto. $prefijo: nombre del input ("motos[12]" / "nueva_moto" / "moto"),
    $clave: clave de validación ("motos.12" / "nueva_moto" / "moto"), $moto: Moto|null.
--}}
@php
    $idBase = str_replace(['[', ']', '.'], ['_', '', '_'], $clave);
@endphp
<div class="grid grid-cols-1 gap-4 rounded-md border border-gray-200 p-4 sm:grid-cols-4">
    <div>
        <label for="{{ $idBase }}_placa" class="block text-sm font-medium text-gray-700">Placa</label>
        <input type="text" name="{{ $prefijo }}[placa]" id="{{ $idBase }}_placa"
               value="{{ old("{$clave}.placa", $moto?->placa) }}" @required($obligatoria)
               placeholder="ABC12D" maxlength="8" autocomplete="off"
               x-data @input="$el.value = $el.value.toUpperCase()"
               class="mt-1 block w-full rounded-md border-gray-300 font-mono uppercase shadow-sm">
        @error("{$clave}.placa") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="{{ $idBase }}_marca" class="block text-sm font-medium text-gray-700">Marca</label>
        <input type="text" name="{{ $prefijo }}[marca]" id="{{ $idBase }}_marca"
               value="{{ old("{$clave}.marca", $moto?->marca) }}" @required($obligatoria)
               placeholder="Yamaha" list="marcas-moto"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        @error("{$clave}.marca") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="{{ $idBase }}_modelo" class="block text-sm font-medium text-gray-700">Modelo</label>
        <input type="text" name="{{ $prefijo }}[modelo]" id="{{ $idBase }}_modelo"
               value="{{ old("{$clave}.modelo", $moto?->modelo) }}" @required($obligatoria)
               placeholder="FZ 2.0"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        @error("{$clave}.modelo") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="{{ $idBase }}_anio" class="block text-sm font-medium text-gray-700">Año <span class="font-normal text-gray-400">(opc.)</span></label>
        <input type="number" name="{{ $prefijo }}[anio]" id="{{ $idBase }}_anio"
               value="{{ old("{$clave}.anio", $moto?->anio) }}"
               min="1950" max="{{ now()->year + 1 }}" placeholder="{{ now()->year }}"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        @error("{$clave}.anio") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
</div>

@once
    <datalist id="marcas-moto">
        @foreach(['AKT', 'Auteco', 'Bajaj', 'Hero', 'Honda', 'KTM', 'Kymco', 'Suzuki', 'TVS', 'Victory', 'Yamaha'] as $marca)
            <option value="{{ $marca }}">
        @endforeach
    </datalist>
@endonce
