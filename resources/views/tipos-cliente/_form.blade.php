{{--
    Advertencia en vivo: mientras se escribe el %, consulta tipos-cliente.analizar
    y muestra en ámbar cuántos repuestos quedarían bajo el costo (no bloquea:
    "precio distribuidor" puede ser margen cero a propósito). Fuera de 0–100%
    se bloquea el guardado (y el backend lo rechaza igual).
--}}
@csrf
<div x-data="analisisDescuento({
        porcentaje: @js((string) old('descuento_repuestos_pct', $tipoCliente->descuento_repuestos_pct !== null ? rtrim(rtrim($tipoCliente->descuento_repuestos_pct, '0'), '.') : '0')),
        url: @js(route('tipos-cliente.analizar')),
        inicial: @js($analisis ?? null),
     })">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="sm:col-span-2">
            <label for="nombre" class="block text-sm font-medium text-gray-700">Nombre</label>
            <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $tipoCliente->nombre) }}" required maxlength="100"
                   placeholder="Ej. Distribuidor, Flota empresarial"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            @error('nombre') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="descuento_repuestos_pct" class="block text-sm font-medium text-gray-700">Descuento en repuestos</label>
            <div class="mt-1 flex rounded-md shadow-sm">
                <input type="text" inputmode="decimal" name="descuento_repuestos_pct" id="descuento_repuestos_pct"
                       x-model="porcentaje" @input.debounce.400ms="analizar()" required
                       class="block w-full rounded-l-md border-gray-300 text-right">
                <span class="inline-flex items-center rounded-r-md border border-l-0 border-gray-300 bg-gray-50 px-3 text-sm text-gray-500">%</span>
            </div>
            @error('descuento_repuestos_pct') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="sm:col-span-3">
            <label for="descripcion" class="block text-sm font-medium text-gray-700">Descripción <span class="font-normal text-gray-400">(opcional)</span></label>
            <textarea name="descripcion" id="descripcion" rows="2" maxlength="1000"
                      placeholder="A quién aplica y por qué"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('descripcion', $tipoCliente->descripcion) }}</textarea>
            @error('descripcion') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="sm:col-span-3">
            <input type="hidden" name="activo" value="0">
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="activo" value="1" @checked(old('activo', $tipoCliente->activo))
                       @disabled($tipoCliente->exists && $tipoCliente->esRegular()) class="rounded border-gray-300">
                Activo (se puede asignar a clientes)
            </label>
            @if($tipoCliente->exists && $tipoCliente->esRegular())
                <input type="hidden" name="activo" value="1">
                <p class="mt-1 text-xs text-gray-500">Regular no se puede desactivar: es el que se aplica a clientes sin tipo.</p>
            @endif
            @error('activo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    {{-- Bloqueo: fuera de rango --}}
    <div x-show="fueraDeRango" x-cloak class="mt-4 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-800">
        El descuento debe estar entre 0% y 100%. Más de 100% dejaría precios negativos.
    </div>

    {{-- Advertencia: repuestos bajo el costo --}}
    <div x-show="! fueraDeRango && analisis && analisis.bajo_costo > 0" x-cloak
         class="mt-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
        <p class="font-semibold">
            ⚠ Este descuento deja <span x-text="analisis?.bajo_costo"></span>
            de <span x-text="analisis?.total_repuestos"></span> repuestos por debajo del costo.
        </p>
        <p class="mt-1 text-xs">Puedes guardarlo igual (por ejemplo, para dar precio de distribuidor), pero el taller perdería dinero en esos repuestos.</p>
        <ul class="mt-2 space-y-0.5 text-xs">
            <template x-for="ejemplo in analisis?.ejemplos ?? []" :key="ejemplo.nombre">
                <li>
                    <span class="font-medium" x-text="ejemplo.nombre"></span>:
                    se cobraría <span x-text="pesos(ejemplo.precio_con_descuento)"></span>,
                    cuesta <span x-text="pesos(ejemplo.precio_costo)"></span>
                </li>
            </template>
        </ul>
    </div>

    <p x-show="! fueraDeRango && analisis && analisis.bajo_costo === 0 && Number(porcentajeNumerico) > 0" x-cloak
       class="mt-4 text-sm text-green-700">
        ✓ Con este descuento ningún repuesto queda por debajo del costo.
    </p>

    <div class="mt-6 flex justify-end gap-3">
        <a href="{{ route('tipos-cliente.index') }}" class="rounded-md border px-4 py-2 text-sm">Cancelar</a>
        <button type="submit" :disabled="fueraDeRango"
                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40">
            Guardar
        </button>
    </div>
</div>

<script>
    function analisisDescuento({ porcentaje, url, inicial }) {
        return {
            porcentaje,
            analisis: inicial,
            controlador: null,

            get porcentajeNumerico() { return Number(String(this.porcentaje).replace(',', '.')); },
            get fueraDeRango() {
                const valor = this.porcentajeNumerico;
                return String(this.porcentaje).trim() === '' || Number.isNaN(valor) || valor < 0 || valor > 100;
            },

            init() {
                if (! this.analisis) this.analizar();
            },

            async analizar() {
                if (this.fueraDeRango) { this.analisis = null; return; }

                this.controlador?.abort();
                this.controlador = new AbortController();

                try {
                    const respuesta = await fetch(`${url}?porcentaje=${encodeURIComponent(this.porcentajeNumerico)}`, {
                        headers: { 'Accept': 'application/json' },
                        signal: this.controlador.signal,
                    });
                    if (respuesta.ok) this.analisis = await respuesta.json();
                } catch (e) {
                    // Abortada por una tecla más reciente, o sin conexión: la validación del backend sigue aplicando.
                }
            },

            pesos(valor) { return '$' + Math.round(valor).toLocaleString('es-CO'); },
        };
    }
</script>
