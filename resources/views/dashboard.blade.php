{{--
    Dashboard ejecutivo (solo administración). Reactivo sin recargar:
    - consulta GET /api/dashboard?periodo=X cada 15 s, solo con la pestaña visible;
    - refresco inmediato si en otra pestaña se paga, liquida o crea una orden (BroadcastChannel).
    Todo se pinta con Alpine desde `datos` (misma forma que devuelve el endpoint).

    Visualización: indicadores en tarjetas y barras horizontales de un solo tono,
    siempre con etiqueta y valor visibles; rojo/amarillo solo para el semáforo.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold text-gray-800">Dashboard</h2>
            {{-- Fuera de x-data: onclick nativo. --}}
            <button type="button" onclick="window.dispatchEvent(new CustomEvent('abrir-crear-orden'))"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                + Crear orden manual
            </button>
        </div>
    </x-slot>

    <div class="py-6"
         x-data="dashboardEjecutivo(@js($datos), @js(route('dashboard.datos')))"
         @orden-actualizada.window="actualizar()">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- Filtro de periodo (una sola fila arriba) + estado de la actualización --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="inline-flex rounded-md shadow-sm" role="group" aria-label="Periodo">
                    <button type="button" @click="cambiarPeriodo('semanal')"
                            :class="periodo === 'semanal' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                            :aria-pressed="periodo === 'semanal'"
                            class="rounded-l-md border border-gray-300 px-4 py-2 text-sm font-medium">Semana</button>
                    <button type="button" @click="cambiarPeriodo('mensual')"
                            :class="periodo === 'mensual' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                            :aria-pressed="periodo === 'mensual'"
                            class="-ml-px rounded-r-md border border-gray-300 px-4 py-2 text-sm font-medium">Mes</button>
                </div>
                <p class="text-xs text-gray-500" aria-live="polite">
                    <span x-text="`${d.periodo.etiqueta}: ${d.periodo.desde} – ${d.periodo.hasta}`"></span>
                    · <span x-show="! error">Actualizado <span x-text="d.generado"></span></span>
                    <span x-show="error" x-cloak class="text-amber-700">Sin conexión, reintentando…</span>
                    <span x-show="cargando" x-cloak>· actualizando…</span>
                </p>
            </div>

            {{-- Indicadores principales --}}
            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Indicadores">
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Caja <span x-text="d.periodo.etiqueta.toLowerCase()"></span></p>
                    <p class="mt-1 text-3xl font-bold text-gray-900" x-text="pesos(d.caja.total)"></p>
                    <p class="mt-1 text-xs text-gray-500">
                        <span x-text="`${d.caja.ordenes} ${d.caja.ordenes === 1 ? 'orden pagada' : 'órdenes pagadas'}`"></span>
                    </p>
                    <dl class="mt-3 space-y-0.5 border-t pt-2 text-xs text-gray-600">
                        <div class="flex justify-between"><dt>Sin IVA</dt><dd x-text="pesos(d.caja.sin_iva)"></dd></div>
                        <div class="flex justify-between"><dt>IVA</dt><dd x-text="pesos(d.caja.iva)"></dd></div>
                    </dl>
                </div>

                <a href="{{ route('ordenes.kanban') }}" class="block rounded-lg bg-white p-5 shadow-sm hover:ring-2 hover:ring-indigo-200">
                    <p class="text-sm text-gray-500">Cuentas por cobrar</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900" x-text="pesos(d.por_cobrar.total)"></p>
                    <p class="mt-1 text-xs text-gray-500"
                       x-text="`${d.por_cobrar.ordenes} ${d.por_cobrar.ordenes === 1 ? 'orden lista' : 'órdenes listas'} para reclamar`"></p>
                    <p class="mt-3 border-t pt-2 text-xs text-gray-600" x-show="d.por_cobrar.dias_mas_antigua !== null"
                       x-text="`La más antigua lleva ${d.por_cobrar.dias_mas_antigua} ${d.por_cobrar.dias_mas_antigua === 1 ? 'día' : 'días'} sin pagar`"></p>
                </a>

                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Motos en riesgo</p>
                    <div class="mt-2 flex items-end gap-6">
                        <div>
                            <p class="flex items-center gap-1.5 text-3xl font-bold text-gray-900">
                                <span class="h-3 w-3 rounded-full bg-red-500" aria-hidden="true"></span>
                                <span x-text="d.riesgo.rojas"></span>
                            </p>
                            <p class="text-xs text-gray-500" x-text="`En rojo (más de ${d.riesgo.umbral_rojo} días)`"></p>
                        </div>
                        <div>
                            <p class="flex items-center gap-1.5 text-3xl font-bold text-gray-900">
                                <span class="h-3 w-3 rounded-full bg-yellow-400" aria-hidden="true"></span>
                                <span x-text="d.riesgo.amarillas"></span>
                            </p>
                            <p class="text-xs text-gray-500">En amarillo</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">Descuentos otorgados</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900" x-text="pesos(totalDescuentos)"></p>
                    <p class="mt-1 text-xs text-gray-500">En repuestos, a clientes con tipo especial.</p>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                {{-- Métodos de pago --}}
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900">Caja por método de pago</h3>
                    <ul class="mt-4 space-y-3">
                        <template x-for="m in d.metodos_pago" :key="m.clave">
                            <li :title="`${m.etiqueta}: ${pesos(m.total)} en ${m.ordenes} ${m.ordenes === 1 ? 'orden' : 'órdenes'}`">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-700" x-text="m.etiqueta"></span>
                                    <span class="font-medium text-gray-900">
                                        <span x-text="pesos(m.total)"></span>
                                        <span class="ml-1 text-xs font-normal text-gray-500" x-text="porcentaje(m.total, d.caja.total)"></span>
                                    </span>
                                </div>
                                <div class="mt-1 h-2 rounded-full bg-gray-100">
                                    <div class="h-2 rounded-full bg-indigo-500" :style="`width: ${ancho(m.total, maximo(d.metodos_pago, 'total'))}%`"></div>
                                </div>
                            </li>
                        </template>
                    </ul>
                    <p x-show="! d.caja.ordenes" class="mt-4 text-sm text-gray-500">Aún no hay pagos en el periodo.</p>
                </div>

                {{-- Mano de obra vs repuestos --}}
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900">Ingresos: mano de obra vs repuestos</h3>
                    <ul class="mt-4 space-y-3">
                        <template x-for="fila in [{ etiqueta: 'Mano de obra', valor: d.ingresos.mano_obra }, { etiqueta: 'Repuestos', valor: d.ingresos.repuestos }]" :key="fila.etiqueta">
                            <li :title="`${fila.etiqueta}: ${pesos(fila.valor)}`">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-700" x-text="fila.etiqueta"></span>
                                    <span class="font-medium text-gray-900">
                                        <span x-text="pesos(fila.valor)"></span>
                                        <span class="ml-1 text-xs font-normal text-gray-500" x-text="porcentaje(fila.valor, d.ingresos.mano_obra + d.ingresos.repuestos)"></span>
                                    </span>
                                </div>
                                <div class="mt-1 h-2 rounded-full bg-gray-100">
                                    <div class="h-2 rounded-full bg-indigo-500" :style="`width: ${ancho(fila.valor, Math.max(d.ingresos.mano_obra, d.ingresos.repuestos))}%`"></div>
                                </div>
                            </li>
                        </template>
                    </ul>
                    <p class="mt-4 text-xs text-gray-500">Órdenes pagadas en el periodo. Repuestos con el descuento del cliente ya aplicado.</p>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                {{-- Rendimiento de mecánicos --}}
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900">Mano de obra por mecánico</h3>
                    <p class="text-xs text-gray-500">Base para comisiones: mano de obra de órdenes pagadas en el periodo.</p>
                    <table class="mt-3 w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-500">
                                <th class="py-1 font-medium">Mecánico</th>
                                <th class="py-1 text-right font-medium">Órdenes</th>
                                <th class="w-2/5 py-1 text-right font-medium">Mano de obra</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <template x-for="m in d.mecanicos" :key="m.nombre">
                                <tr>
                                    <td class="py-2">
                                        <span class="text-gray-900" x-text="m.nombre"></span>
                                        <span x-show="m.tipo === 'ayudante'" class="ml-1 text-xs text-gray-500">(ayudante)</span>
                                    </td>
                                    <td class="py-2 text-right text-gray-600" x-text="m.ordenes"></td>
                                    <td class="py-2 text-right">
                                        <span class="font-medium text-gray-900" x-text="pesos(m.mano_obra)"></span>
                                        <div class="ml-auto mt-1 h-1.5 rounded-full bg-gray-100">
                                            <div class="ml-auto h-1.5 rounded-full bg-indigo-500" :style="`width: ${ancho(m.mano_obra, maximo(d.mecanicos, 'mano_obra'))}%`"></div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <p x-show="! d.mecanicos.length" class="mt-2 text-sm text-gray-500">Sin órdenes pagadas en el periodo.</p>
                </div>

                {{-- Descuentos por tipo de cliente --}}
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900">Descuentos por tipo de cliente</h3>
                    <table class="mt-3 w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-500">
                                <th class="py-1 font-medium">Tipo</th>
                                <th class="py-1 text-right font-medium">Órdenes</th>
                                <th class="py-1 text-right font-medium">Descuento</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <template x-for="t in d.descuentos" :key="t.tipo">
                                <tr>
                                    <td class="py-2 text-gray-900" x-text="t.tipo"></td>
                                    <td class="py-2 text-right text-gray-600" x-text="t.ordenes"></td>
                                    <td class="py-2 text-right font-medium text-gray-900" x-text="pesos(t.descuento)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <p x-show="! d.descuentos.length" class="mt-2 text-sm text-gray-500">No se dieron descuentos en el periodo.</p>
                </div>
            </section>

            {{-- Motos en riesgo: detalle --}}
            <section class="rounded-lg border border-red-200 bg-white p-5 shadow-sm" x-show="d.riesgo.lista.length">
                <h3 class="flex items-center gap-2 text-sm font-semibold text-red-800">
                    <span class="h-2.5 w-2.5 rounded-full bg-red-500" aria-hidden="true"></span>
                    Las más demoradas
                </h3>
                <ul class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <template x-for="o in d.riesgo.lista" :key="o.id">
                        <li>
                            <a :href="o.url" class="block rounded-md border border-l-4 border-gray-200 border-l-red-500 p-3 text-sm hover:bg-red-50">
                                <span class="font-mono font-semibold text-gray-900" x-text="o.placa"></span>
                                <span class="float-right text-xs font-medium text-red-700" x-text="`${o.dias} días`"></span>
                                <span class="block text-xs text-gray-600" x-text="`${o.estado}${o.mecanico ? ' · ' + o.mecanico : ''}`"></span>
                            </a>
                        </li>
                    </template>
                </ul>
            </section>

            <section class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                {{-- Bitácora --}}
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900">Últimas órdenes pagadas</h3>
                    <ul class="mt-3 divide-y">
                        <template x-for="o in d.bitacora" :key="o.id">
                            <li class="py-3 text-sm">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <a :href="o.url" class="hover:text-indigo-700">
                                        <span class="font-mono font-semibold text-gray-900" x-text="o.placa"></span>
                                        <span class="text-gray-600" x-text="` · ${o.cliente ?? ''}`"></span>
                                    </a>
                                    <span class="font-medium text-gray-900" x-text="pesos(o.total)"></span>
                                </div>
                                <p class="text-xs text-gray-500"
                                   x-text="[o.numero_factura ? `Factura N.º ${o.numero_factura}` : null, o.pagada, o.metodo_pago, o.mecanico].filter(Boolean).join(' · ')"></p>
                                <p class="mt-1 rounded bg-gray-50 px-2 py-1 text-xs text-gray-700" x-show="o.observaciones" x-text="o.observaciones"></p>
                            </li>
                        </template>
                    </ul>
                    <p x-show="! d.bitacora.length" class="mt-2 text-sm text-gray-500">Aún no hay órdenes pagadas.</p>
                </div>

                {{-- Stock crítico por distribuidor --}}
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900">Stock crítico</h3>
                        <a href="{{ route('inventario.index') }}" class="text-xs text-indigo-600 hover:underline">Ver inventario</a>
                    </div>
                    <div class="mt-3 space-y-4">
                        {{-- Hasta 12 por distribuidor; "Ver todos" despliega el resto (el estado se conserva entre consultas). --}}
                        <template x-for="g in d.stock" :key="g.distribuidor ?? 'sin-distribuidor'">
                            <div x-data="{ todos: false }">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-medium text-gray-800">
                                        <span x-text="g.distribuidor ?? 'Sin distribuidor asignado'"></span>
                                        <span class="ml-1 text-xs font-normal text-gray-500"
                                              x-text="`${g.repuestos.length} bajo el mínimo${g.agotados ? ` · ${g.agotados} ${g.agotados === 1 ? 'agotado' : 'agotados'}` : ''}`"></span>
                                    </p>
                                    <a x-show="g.url_solicitud" :href="g.url_solicitud"
                                       class="shrink-0 rounded-md border border-indigo-200 px-2.5 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">
                                        Solicitar pedido
                                    </a>
                                </div>
                                <ul class="mt-1 divide-y divide-gray-100 text-xs">
                                    <template x-for="r in (todos ? g.repuestos : g.repuestos.slice(0, 12))" :key="r.id">
                                        <li class="flex items-start justify-between gap-3 py-1.5">
                                            <span class="min-w-0">
                                                <span class="text-gray-800" x-text="r.nombre"></span>
                                                <span class="ml-1 font-mono text-gray-500" x-show="r.codigo" x-text="r.codigo"></span>
                                                <span class="ml-1 italic text-gray-400" x-show="! r.codigo">sin código</span>
                                                <span class="block truncate text-gray-400" x-show="r.compatibilidad" x-text="r.compatibilidad"></span>
                                            </span>
                                            <span class="shrink-0 whitespace-nowrap font-medium"
                                                  :class="r.stock === 0 ? 'text-red-700' : 'text-amber-700'"
                                                  x-text="r.stock === 0 ? 'Agotado' : `Quedan ${r.stock} (mín. ${r.minimo})`"></span>
                                        </li>
                                    </template>
                                </ul>
                                <button type="button" x-show="g.repuestos.length > 12" @click="todos = ! todos"
                                        class="mt-1 text-xs font-medium text-indigo-600 hover:underline"
                                        x-text="todos ? 'Ver menos' : `Ver todos (${g.repuestos.length})`"></button>
                            </div>
                        </template>
                    </div>
                    <p x-show="! d.stock.length" class="mt-2 text-sm text-gray-500">Ningún repuesto está por debajo del mínimo.</p>
                </div>
            </section>
        </div>
    </div>

    @include('ordenes._crear-modal')

    @push('scripts')
    <script>
        function dashboardEjecutivo(inicial, rutaDatos) {
            const CADA_MS = 15000;

            return {
                d: inicial,
                periodo: inicial.periodo.clave,
                cargando: false,
                pendiente: false,
                error: false,
                temporizador: null,

                init() {
                    // Consulta periódica solo con la pestaña visible; al volver, se pone al día.
                    this.temporizador = setInterval(() => {
                        if (document.visibilityState === 'visible') this.actualizar();
                    }, CADA_MS);
                    document.addEventListener('visibilitychange', () => {
                        if (document.visibilityState === 'visible') this.actualizar();
                    });

                    // Pago / liquidación / orden nueva en otra pestaña: refresco inmediato.
                    Servimotos.alRecibirDeOtraPestana(() => this.actualizar());
                },

                async actualizar() {
                    // Una consulta a la vez; si llega otra petición, se repite al terminar.
                    if (this.cargando) {
                        this.pendiente = true;
                        return;
                    }
                    this.cargando = true;

                    const { ok, datos } = await Servimotos.pedir(`${rutaDatos}?periodo=${this.periodo}`, 'GET');

                    this.cargando = false;
                    this.error = ! ok;
                    if (ok && datos.periodo?.clave === this.periodo) this.d = datos;

                    if (this.pendiente) {
                        this.pendiente = false;
                        this.actualizar();
                    }
                },

                cambiarPeriodo(periodo) {
                    if (periodo === this.periodo) return;
                    this.periodo = periodo;
                    this.actualizar();
                },

                get totalDescuentos() {
                    return this.d.descuentos.reduce((suma, t) => suma + t.descuento, 0);
                },

                pesos: (valor) => Servimotos.pesos(valor),

                maximo(filas, campo) {
                    return Math.max(0, ...filas.map((f) => f[campo]));
                },

                /** Ancho de barra en % del máximo; mínimo visible si el valor es > 0. */
                ancho(valor, maximo) {
                    if (! maximo || ! valor) return 0;
                    return Math.max((valor / maximo) * 100, 2);
                },

                porcentaje(valor, total) {
                    return total ? `${Math.round((valor / total) * 100)} %` : '';
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
