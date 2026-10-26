{{--
    Modal de liquidación en dos pasos (Kanban y detalle de la orden). Se abre con:
        window.dispatchEvent(new CustomEvent('abrir-liquidacion', { detail: tarjeta }))
    donde `tarjeta` es App\Support\TarjetaOrden::datos(). Al guardar emite
    'orden-actualizada' con la tarjeta nueva.

    - Mecánico → "Registrar trabajo" (POST ordenes.trabajo): repuestos, cantidades
      y observaciones. Sin precios, sin mano de obra, sin totales.
    - Administradora → "Emitir factura" (POST facturas.generar): parte del trabajo
      registrado, fija la mano de obra y ve la vista previa de totales.

    Los totales son SOLO una vista previa: el backend recalcula precios,
    descuento e IVA (regla 5) y solo recibe repuesto + cantidad.

    Requiere $iva = TarjetaOrden::iva($config).
--}}
@php $verDinero = App\Support\TarjetaOrden::veDinero(auth()->user()); @endphp
@include('partials.js-ordenes')

<div x-data="liquidacionModal(@js($iva), @js($verDinero ? route('facturas.generar', ['orden' => '__ID__']) : route('ordenes.trabajo', ['orden' => '__ID__'])), @js(route('inventario.buscar')), @js($verDinero))"
     @abrir-liquidacion.window="abrir($event.detail)"
     @keydown.escape.window="cerrar()">
    <div x-show="abierto" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="titulo-liquidacion">
        <div class="fixed inset-0 bg-gray-900/50" @click="cerrar()"></div>

        <form class="relative my-8 w-full max-w-3xl rounded-lg bg-white shadow-xl" @submit.prevent="liquidar()">
            <div class="border-b px-6 py-4">
                <h3 id="titulo-liquidacion" class="text-lg font-semibold text-gray-900">
                    {{ $verDinero ? 'Emitir factura' : 'Registrar trabajo' }} · <span x-text="orden?.placa"></span>
                </h3>
                <p class="text-sm text-gray-600">
                    <span x-text="orden?.cliente"></span>
                    @if($verDinero)
                        <template x-if="orden?.descuento_pct > 0">
                            <span class="ml-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700"
                                  x-text="`Descuento ${orden.descuento_pct} % en repuestos`"></span>
                        </template>
                    @endif
                    <template x-if="orden?.trabajo_registrado">
                        <span class="ml-1 rounded-full bg-sky-100 px-2 py-0.5 text-xs text-sky-700">Trabajo registrado</span>
                    </template>
                    <template x-if="orden?.numero_factura">
                        <span class="ml-1 text-xs text-gray-500" x-text="`· Reemite la factura N.º ${orden.numero_factura}`"></span>
                    </template>
                </p>
            </div>

            <div class="space-y-5 px-6 py-4">
                <div x-show="errores.length" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert">
                    <ul class="list-inside list-disc">
                        <template x-for="error in errores" :key="error"><li x-text="error"></li></template>
                    </ul>
                </div>

                {{-- Repuestos --}}
                <div>
                    <label for="buscar-repuesto" class="block text-sm font-medium text-gray-700">Repuestos usados</label>
                    <div class="relative mt-1">
                        <input type="search" id="buscar-repuesto" x-model="busqueda" @input.debounce.300ms="buscar()"
                               placeholder="Buscar por nombre, código o moto (mín. 2 letras)" autocomplete="off"
                               class="block w-full rounded-md border-gray-300 shadow-sm">
                        <div x-show="resultados.length" @click.outside="resultados = []"
                             class="absolute z-10 mt-1 max-h-64 w-full overflow-y-auto rounded-md border bg-white shadow-lg">
                            <template x-for="r in resultados" :key="r.id">
                                <button type="button" @click="agregar(r)" :disabled="! r.disponible"
                                        class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-50">
                                    <span>
                                        <span class="font-medium text-gray-900" x-text="r.nombre"></span>
                                        <span class="block text-xs text-gray-500" x-text="[r.codigo_sku, r.compatibilidad].filter(Boolean).join(' · ')"></span>
                                    </span>
                                    <span class="whitespace-nowrap text-right text-xs">
                                        @if($verDinero)
                                            <span class="block font-medium text-gray-900" x-text="Servimotos.pesos(r.precio_venta)"></span>
                                        @endif
                                        <span :class="r.disponible ? 'text-gray-500' : 'text-red-600'" x-text="r.disponible ? `Stock: ${r.stock_actual}` : 'Sin stock'"></span>
                                    </span>
                                </button>
                            </template>
                        </div>
                        <p x-show="buscando" class="mt-1 text-xs text-gray-500">Buscando…</p>
                        <p x-show="sinResultados" class="mt-1 text-xs text-gray-500">No se encontraron repuestos.</p>
                    </div>

                    <table class="mt-3 w-full text-sm" x-show="items.length">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-500">
                                <th class="py-1">Repuesto</th>
                                <th class="w-24 py-1 text-center">Cant.</th>
                                @if($verDinero)
                                    <th class="w-28 py-1 text-right">Precio</th>
                                    <th class="w-28 py-1 text-right">Subtotal</th>
                                @endif
                                <th class="w-8"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <template x-for="(item, i) in items" :key="item.id">
                                <tr>
                                    <td class="py-2">
                                        <span class="font-medium text-gray-900" x-text="item.nombre"></span>
                                        <span class="block text-xs" :class="item.cantidad > item.stock ? 'text-red-600' : 'text-gray-500'"
                                              x-text="item.cantidad > item.stock ? `Solo hay ${item.stock} en stock` : (item.sku ?? '')"></span>
                                    </td>
                                    <td class="py-2 text-center">
                                        <input type="number" min="1" :max="item.stock" x-model.number="item.cantidad"
                                               class="w-20 rounded-md border-gray-300 text-center text-sm shadow-sm" aria-label="Cantidad">
                                    </td>
                                    @if($verDinero)
                                        <td class="py-2 text-right">
                                            <span x-text="Servimotos.pesos(precioFinal(item.precio_venta))"></span>
                                            <span x-show="orden?.descuento_pct > 0" class="block text-xs text-gray-400 line-through" x-text="Servimotos.pesos(item.precio_venta)"></span>
                                        </td>
                                        <td class="py-2 text-right font-medium" x-text="Servimotos.pesos(precioFinal(item.precio_venta) * (item.cantidad || 0))"></td>
                                    @endif
                                    <td class="py-2 text-right">
                                        <button type="button" @click="items.splice(i, 1)" class="text-gray-400 hover:text-red-600" aria-label="Quitar repuesto">✕</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <p x-show="! items.length" class="mt-2 text-xs text-gray-500">Sin repuestos. Si el servicio fue solo mano de obra, déjalo así.</p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    {{-- La mano de obra la fija la administradora al emitir la factura. --}}
                    @if($verDinero)
                    <div>
                        <label for="mano-obra" class="block text-sm font-medium text-gray-700">Mano de obra</label>
                        <div class="relative mt-1">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-500">$</span>
                            <input type="number" id="mano-obra" min="0" step="1" x-model.number="manoObra" required
                                   class="block w-full rounded-md border-gray-300 pl-7 shadow-sm">
                        </div>
                    </div>
                    @endif
                    <div class="{{ $verDinero ? 'sm:col-span-2' : 'sm:col-span-3' }}">
                        <label for="observaciones-tecnicas" class="block text-sm font-medium text-gray-700">
                            Observaciones técnicas <span class="text-red-600">*</span>
                        </label>
                        <textarea id="observaciones-tecnicas" rows="3" x-model="observaciones" required minlength="5" maxlength="5000"
                                  placeholder="Qué se encontró y qué se hizo. Aparece en la factura del cliente."
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
                    </div>
                </div>

                {{-- Vista previa de totales (el servidor recalcula). Solo administrativos. --}}
                @if($verDinero)
                <div class="ml-auto w-full max-w-xs rounded-md bg-gray-50 p-3 text-sm">
                    <dl class="space-y-1">
                        <div class="flex justify-between"><dt class="text-gray-600">Repuestos</dt><dd x-text="Servimotos.pesos(totales.repuestosLista)"></dd></div>
                        <div class="flex justify-between text-emerald-700" x-show="totales.descuento > 0">
                            <dt>Descuento</dt><dd x-text="'−' + Servimotos.pesos(totales.descuento)"></dd>
                        </div>
                        <div class="flex justify-between"><dt class="text-gray-600">Mano de obra</dt><dd x-text="Servimotos.pesos(totales.manoObra)"></dd></div>
                        <div class="flex justify-between border-t pt-1"><dt class="text-gray-600">Subtotal</dt><dd x-text="Servimotos.pesos(totales.subtotal)"></dd></div>
                        <div class="flex justify-between"><dt class="text-gray-600">Base gravable</dt><dd x-text="Servimotos.pesos(totales.base)"></dd></div>
                        <div class="flex justify-between">
                            <dt class="text-gray-600" x-text="`IVA (${iva.tasa_iva} %)${iva.precios_incluyen_iva ? ' incluido' : ''}`"></dt>
                            <dd x-text="Servimotos.pesos(totales.iva)"></dd>
                        </div>
                        <div class="flex justify-between border-t pt-1 text-base font-bold text-gray-900"><dt>Total</dt><dd x-text="Servimotos.pesos(totales.total)"></dd></div>
                    </dl>
                    <p class="mt-2 text-xs text-gray-500">Vista previa. Los precios y el IVA definitivos los calcula el sistema al confirmar.</p>
                </div>
                @else
                <p class="text-xs text-gray-500">La orden queda en "Terminada" con el trabajo registrado. La administradora fija la mano de obra y emite la factura.</p>
                @endif
            </div>

            <div class="flex justify-end gap-3 border-t px-6 py-4">
                <button type="button" @click="cerrar()" class="rounded-md border px-4 py-2 text-sm">Cancelar</button>
                <button type="submit" :disabled="enviando"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">
                    <span x-show="! enviando">{{ $verDinero ? 'Emitir factura' : 'Registrar trabajo' }}</span>
                    <span x-show="enviando" x-cloak>Guardando…</span>
                </button>
            </div>
        </form>
    </div>
</div>

@once
    <script>
        function liquidacionModal(iva, rutaLiquidar, rutaBuscar, emiteFactura) {
            return {
                iva,
                emiteFactura,
                abierto: false,
                orden: null,
                busqueda: '',
                resultados: [],
                buscando: false,
                sinResultados: false,
                items: [],
                manoObra: 0,
                observaciones: '',
                errores: [],
                enviando: false,

                abrir(tarjeta) {
                    this.orden = tarjeta;
                    // Parte de lo ya registrado (por el mecánico o en un intento anterior).
                    this.items = (tarjeta.repuestos ?? []).map((r) => ({
                        id: r.id,
                        nombre: r.nombre,
                        sku: r.sku,
                        precio_venta: Number(r.precio_venta ?? 0),
                        stock: r.stock,
                        cantidad: r.cantidad,
                    }));
                    this.busqueda = '';
                    this.resultados = [];
                    this.sinResultados = false;
                    this.errores = [];
                    // Tras una corrección se precargan los valores anteriores.
                    this.manoObra = tarjeta.mano_obra || 0;
                    this.observaciones = tarjeta.observaciones_tecnicas ?? '';
                    this.abierto = true;
                    this.$nextTick(() => document.getElementById('buscar-repuesto')?.focus());
                },

                cerrar() {
                    if (! this.enviando) this.abierto = false;
                },

                async buscar() {
                    const q = this.busqueda.trim();
                    this.sinResultados = false;

                    if (q.length < 2) {
                        this.resultados = [];
                        return;
                    }

                    this.buscando = true;
                    const { ok, datos } = await Servimotos.pedir(`${rutaBuscar}?q=${encodeURIComponent(q)}`, 'GET');
                    this.buscando = false;

                    if (ok && q === this.busqueda.trim()) {
                        this.resultados = datos.data ?? [];
                        this.sinResultados = this.resultados.length === 0;
                    }
                },

                agregar(repuesto) {
                    const existente = this.items.find((i) => i.id === repuesto.id);

                    if (existente) {
                        existente.cantidad++;
                    } else {
                        this.items.push({
                            id: repuesto.id,
                            nombre: repuesto.nombre,
                            sku: repuesto.codigo_sku,
                            precio_venta: Number(repuesto.precio_venta),
                            stock: repuesto.stock_actual,
                            cantidad: 1,
                        });
                    }

                    this.busqueda = '';
                    this.resultados = [];
                    document.getElementById('buscar-repuesto')?.focus();
                },

                /** Misma fórmula de descuento que el modelo TipoCliente (pesos enteros). */
                precioFinal(precio) {
                    const pct = Math.min(Math.max(Number(this.orden?.descuento_pct) || 0, 0), 100);
                    return Math.max(Math.round(precio * (1 - pct / 100)), 0);
                },

                /** Misma lógica que FacturaService::calcularImpuestos. */
                get totales() {
                    const repuestosLista = this.items.reduce((s, i) => s + i.precio_venta * (i.cantidad || 0), 0);
                    const repuestos = this.items.reduce((s, i) => s + this.precioFinal(i.precio_venta) * (i.cantidad || 0), 0);
                    const manoObra = Math.max(Number(this.manoObra) || 0, 0);
                    const subtotal = repuestos + manoObra;
                    const tasa = Number(this.iva.tasa_iva) / 100;

                    let base, ivaValor, total;
                    if (this.iva.precios_incluyen_iva) {
                        base = Math.round(subtotal / (1 + tasa));
                        ivaValor = subtotal - base;
                        total = subtotal;
                    } else {
                        base = subtotal;
                        ivaValor = Math.round(subtotal * tasa);
                        total = subtotal + ivaValor;
                    }

                    return { repuestosLista, descuento: repuestosLista - repuestos, manoObra, subtotal, base, iva: ivaValor, total };
                },

                async liquidar() {
                    this.errores = [];

                    if (this.emiteFactura && ! this.items.length && ! (Number(this.manoObra) > 0)) {
                        this.errores = ['Agrega al menos un repuesto o el valor de la mano de obra.'];
                        return;
                    }

                    this.enviando = true;
                    // Solo repuesto y cantidad: ningún precio sale del navegador.
                    const cuerpo = {
                        items: this.items.map((i) => ({ repuesto_id: i.id, cantidad: i.cantidad })),
                        observaciones_tecnicas: this.observaciones,
                    };
                    // El mecánico nunca envía dinero: la mano de obra solo la manda quien emite.
                    if (this.emiteFactura) cuerpo.mano_obra = Number(this.manoObra) || 0;

                    const { ok, datos } = await Servimotos.pedir(Servimotos.ruta(rutaLiquidar, this.orden.id), 'POST', cuerpo);
                    this.enviando = false;

                    if (! ok) {
                        this.errores = Servimotos.errores(datos);
                        return;
                    }

                    this.abierto = false;
                    window.dispatchEvent(new CustomEvent('orden-actualizada', { detail: { orden: datos.orden, mensaje: datos.message } }));
                },
            };
        }
    </script>
@endonce
