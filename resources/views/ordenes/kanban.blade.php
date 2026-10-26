@use('App\Support\TarjetaOrden')
@php
    // Columnas a las que no se llega arrastrando y de las que no se sale:
    // listo_para_reclamar (liquidando / corrigiendo) y pagada (final).
    $bloqueadas = [
        'listo_para_reclamar' => 'Se llega liquidando. Se sale pagando o corrigiendo la factura.',
        'pagada' => 'Solo la administradora registra el pago.',
    ];
    $esAdministrativo = auth()->user()->esAdministrativo();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold text-gray-800">Tablero de órdenes</h2>
            @can('create', App\Models\OrdenServicio::class)
                {{-- Fuera de x-data: onclick nativo. --}}
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('abrir-crear-orden'))"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                    + Crear orden manual
                </button>
            @endcan
        </div>
    </x-slot>

    <div class="py-6"
         x-data="kanbanBoard({
             tarjetas: @js($tarjetas),
             mecanicos: @js($mecanicos),
             etiquetas: @js(TarjetaOrden::ETIQUETAS_ESTADO),
             bloqueadas: @js(array_keys($bloqueadas)),
             esAdministrativo: @js($esAdministrativo),
             rutaEstado: @js(route('ordenes.estado', ['orden' => '__ID__'])),
         })"
         @orden-actualizada.window="actualizada($event.detail)"
         @click="accion($event)">
        <div class="mx-auto max-w-full px-4 sm:px-6 lg:px-8">

            <div class="mb-4 flex flex-wrap items-center gap-4 text-sm text-gray-600">
                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-red-500"></span><strong x-text="contarSemaforo('rojo')"></strong> en rojo</span>
                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-yellow-400"></span><strong x-text="contarSemaforo('amarillo')"></strong> en amarillo</span>
                <span class="text-xs text-gray-400">Días en el taller desde el ingreso.</span>
            </div>

            <div x-show="aviso" x-transition x-cloak role="status"
                 :class="aviso?.tipo === 'error' ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700'"
                 class="mb-4 rounded-md p-4 text-sm" x-text="aviso?.texto"></div>

            <div class="flex gap-4 overflow-x-auto pb-4">
                @foreach($columnas as $estado)
                    @php $bloqueada = array_key_exists($estado, $bloqueadas); @endphp
                    <section class="relative w-72 flex-shrink-0 rounded-lg p-3 transition"
                             :class="arrastrando && @js($bloqueada) ? 'bg-gray-200 opacity-60' : 'bg-gray-100'"
                             aria-labelledby="col-{{ $estado }}">
                        <h3 id="col-{{ $estado }}" class="mb-1 flex items-center justify-between text-sm font-semibold text-gray-700">
                            {{ TarjetaOrden::ETIQUETAS_ESTADO[$estado] }}
                            <span class="rounded-full bg-gray-300 px-2 py-0.5 text-xs" x-text="contar(@js($estado))"></span>
                        </h3>
                        @if($bloqueada)
                            <p class="mb-2 flex items-start gap-1 text-xs text-gray-500" title="{{ $bloqueadas[$estado] }}">
                                <span aria-hidden="true">🔒</span> {{ $bloqueadas[$estado] }}
                            </p>
                        @endif
                        @if($estado === 'pagada')
                            <p class="mb-2 text-xs text-gray-400">Solo las 20 más recientes.</p>
                        @endif

                        {{-- Las tarjetas las pinta kanbanBoard.render() desde el estado. --}}
                        <div class="kanban-column min-h-[120px] space-y-2" data-estado="{{ $estado }}"></div>
                    </section>
                @endforeach
            </div>
        </div>

        {{-- Modal de transición: mecánico + observaciones para "En revisión", observaciones opcionales para el resto. --}}
        <div x-show="transicion" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="titulo-transicion"
             @keydown.escape.window="cancelarTransicion()">
            <div class="absolute inset-0 bg-gray-900/50" @click="cancelarTransicion()"></div>
            <form class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl" @submit.prevent="confirmarTransicion()">
                <template x-if="transicion">
                    <div>
                        <h3 id="titulo-transicion" class="text-lg font-semibold text-gray-900"
                            x-text="transicion.destino === 'terminada' ? '¿Trabajo terminado?' : `Mover a «${etiquetas[transicion.destino]}»`"></h3>
                        <p class="mt-1 text-sm text-gray-600">
                            <span class="font-mono font-medium" x-text="transicion.tarjeta.placa"></span> ·
                            <span x-text="transicion.tarjeta.cliente"></span>
                        </p>

                        <div x-show="transicion.errores.length" class="mt-3 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert">
                            <template x-for="error in transicion.errores" :key="error"><p x-text="error"></p></template>
                        </div>

                        <template x-if="transicion.pideMecanico">
                            <div class="mt-4">
                                <label for="transicion-mecanico" class="block text-sm font-medium text-gray-700">Mecánico que la revisa <span class="text-red-600">*</span></label>
                                <select id="transicion-mecanico" x-model="transicion.mecanicoId" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="">Selecciona…</option>
                                    <optgroup label="Titulares">
                                        <template x-for="m in mecanicos.filter((m) => m.tipo === 'titular')" :key="m.id">
                                            <option :value="String(m.id)" x-text="m.nombre"></option>
                                        </template>
                                    </optgroup>
                                    <optgroup label="Ayudantes">
                                        <template x-for="m in mecanicos.filter((m) => m.tipo === 'ayudante')" :key="m.id">
                                            <option :value="String(m.id)" x-text="m.nombre"></option>
                                        </template>
                                    </optgroup>
                                </select>
                                <p x-show="! mecanicos.length" class="mt-1 text-xs text-amber-700">No hay mecánicos activos. La administradora debe registrarlos en Mecánicos.</p>
                            </div>
                        </template>

                        <div class="mt-4">
                            <label for="transicion-observaciones" class="block text-sm font-medium text-gray-700">
                                <span x-text="transicion.pideMecanico ? 'Observaciones iniciales' : 'Observaciones'"></span>
                                <span x-show="transicion.pideMecanico" class="text-red-600">*</span>
                                <span x-show="! transicion.pideMecanico" class="text-gray-400">(opcional)</span>
                            </label>
                            <textarea id="transicion-observaciones" rows="3" x-model="transicion.observaciones" maxlength="1000"
                                      :required="transicion.pideMecanico"
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
                        </div>

                        <div class="mt-6 flex justify-end gap-3">
                            <button type="button" @click="cancelarTransicion()" class="rounded-md border px-4 py-2 text-sm">Cancelar</button>
                            <button type="submit" :disabled="transicion.enviando"
                                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                                    x-text="transicion.enviando ? 'Guardando…' : 'Confirmar'"></button>
                        </div>
                    </div>
                </template>
            </form>
        </div>
    </div>

    @include('ordenes._crear-modal')
    @include('facturas._liquidacion-modal')
    @if($esAdministrativo)
        @include('ordenes._pago-modal')
    @endif

    @push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
    <script>
        function kanbanBoard(config) {
            const COLOR_SEMAFORO = { verde: 'bg-green-500', amarillo: 'bg-yellow-400', rojo: 'bg-red-500' };
            const BORDE_SEMAFORO = { verde: 'border-l-green-500', amarillo: 'border-l-yellow-400', rojo: 'border-l-red-500' };

            return {
                ...config,
                arrastrando: false,
                transicion: null,
                aviso: null,
                temporizadorAviso: null,

                init() {
                    document.querySelectorAll('.kanban-column').forEach((columna) => {
                        const bloqueada = this.bloqueadas.includes(columna.dataset.estado);

                        new Sortable(columna, {
                            group: { name: 'ordenes', pull: ! bloqueada, put: ! bloqueada },
                            draggable: '.arrastrable',
                            sort: false,
                            animation: 150,
                            onStart: () => (this.arrastrando = true),
                            onEnd: () => (this.arrastrando = false),
                            onAdd: (evento) => this.soltar(evento),
                        });
                    });

                    this.render();

                    // Pago o liquidación hechos en otra pestaña: se refleja aquí sin recargar.
                    Servimotos.alRecibirDeOtraPestana(({ orden }) => {
                        if (orden) {
                            this.guardar(orden);
                            this.render();
                        }
                    });
                },

                // --- Estado y pintado ---

                contar(estado) {
                    return this.tarjetas.filter((t) => t.estado === estado).length;
                },

                contarSemaforo(color) {
                    return this.tarjetas.filter((t) => t.semaforo === color).length;
                },

                buscarTarjeta(id) {
                    return this.tarjetas.find((t) => t.id === Number(id));
                },

                /** Inserta o reemplaza la tarjeta con lo que respondió el servidor. */
                guardar(tarjeta) {
                    const i = this.tarjetas.findIndex((t) => t.id === tarjeta.id);
                    i === -1 ? this.tarjetas.push(tarjeta) : this.tarjetas.splice(i, 1, tarjeta);
                },

                /** Repinta todas las columnas desde el estado: también devuelve una tarjeta soltada si no se confirmó. */
                render() {
                    document.querySelectorAll('.kanban-column').forEach((columna) => {
                        const tarjetas = this.tarjetas.filter((t) => t.estado === columna.dataset.estado);
                        columna.innerHTML = tarjetas.length
                            ? tarjetas.map((t) => this.html(t)).join('')
                            : '<p class="py-6 text-center text-xs text-gray-400">Sin órdenes</p>';
                    });
                },

                html(t) {
                    const e = Servimotos.esc;
                    const arrastrable = ! this.bloqueadas.includes(t.estado);
                    const semaforo = t.semaforo
                        ? `<span class="inline-flex items-center gap-1 text-xs text-gray-500" title="${t.dias} ${t.dias === 1 ? 'día' : 'días'} en el taller">
                               <span class="h-2.5 w-2.5 rounded-full ${COLOR_SEMAFORO[t.semaforo]}"></span>${t.dias} d</span>`
                        : '';
                    const boton = (accion, texto, clases) =>
                        `<button type="button" data-accion="${accion}" data-id="${t.id}" class="mt-2 w-full rounded px-2 py-1 text-xs font-semibold ${clases}">${texto}</button>`;

                    return `
                        <article class="kanban-card rounded-md border border-l-4 border-gray-200 bg-white p-3 shadow-sm ${t.semaforo ? BORDE_SEMAFORO[t.semaforo] : ''} ${arrastrable ? 'arrastrable cursor-move' : ''}"
                                 data-id="${t.id}">
                            <div class="flex items-start justify-between gap-2">
                                <a href="${e(t.url)}" class="font-mono text-sm font-semibold text-gray-900 hover:text-indigo-700">${e(t.placa)}</a>
                                ${semaforo}
                            </div>
                            <p class="text-xs text-gray-600">${e(t.cliente)}</p>
                            <p class="text-xs text-gray-400">${e(t.moto)}</p>
                            ${t.mecanico ? `<p class="mt-1 text-xs text-indigo-600">🔧 ${e(t.mecanico.nombre)}</p>` : ''}
                            ${t.numero_factura ? `<p class="mt-1 text-xs text-gray-500">Factura N.º ${e(t.numero_factura)}${t.total !== null ? ` · ${Servimotos.pesos(t.total)}` : ''}</p>` : ''}
                            ${t.correcciones ? `<span class="mt-1 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700">Corregida ${t.correcciones} ${t.correcciones === 1 ? 'vez' : 'veces'}</span>` : ''}
                            ${t.estado === 'terminada'
                                ? `<span class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs ${t.trabajo_registrado ? 'bg-sky-100 text-sky-700' : 'bg-gray-100 text-gray-600'}">${t.trabajo_registrado ? 'Trabajo registrado' : 'Falta registrar el trabajo'}</span>`
                                : ''}
@if($esAdministrativo)
                            ${t.estado === 'terminada' ? boton('liquidar', 'Emitir factura', 'bg-indigo-600 text-white hover:bg-indigo-500') : ''}
@else
                            ${t.estado === 'terminada' ? boton('liquidar', t.trabajo_registrado ? 'Editar trabajo' : 'Registrar trabajo', 'bg-indigo-600 text-white hover:bg-indigo-500') : ''}
@endif
@if($esAdministrativo)
                            ${t.estado === 'listo_para_reclamar' ? boton('pagar', 'Marcar como pagada', 'bg-green-600 text-white hover:bg-green-500') : ''}
@endif
                        </article>`;
                },

                avisar(texto, tipo = 'ok') {
                    this.aviso = { texto, tipo };
                    clearTimeout(this.temporizadorAviso);
                    this.temporizadorAviso = setTimeout(() => (this.aviso = null), 5000);
                },

                // --- Botones de las tarjetas (delegación) ---

                accion(evento) {
                    const boton = evento.target.closest('[data-accion]');
                    if (! boton) return;

                    const tarjeta = this.buscarTarjeta(boton.dataset.id);
                    const eventos = { liquidar: 'abrir-liquidacion', pagar: 'abrir-pago' };
                    window.dispatchEvent(new CustomEvent(eventos[boton.dataset.accion], { detail: tarjeta }));
                },

                /** Liquidada, pagada o creada desde un modal: se actualiza sin recargar. */
                actualizada({ orden, mensaje }) {
                    this.guardar(orden);
                    this.render();
                    if (mensaje) this.avisar(mensaje);
                },

                // --- Drag & drop ---

                soltar(evento) {
                    const tarjeta = this.buscarTarjeta(evento.item.dataset.id);
                    const destino = evento.to.dataset.estado;

                    // Mismas reglas que CambiarEstadoRequest / OrdenService.
                    const pideMecanico = destino === 'en_revision'
                        && (tarjeta.estado === 'en_espera_revision' || ! tarjeta.mecanico);

                    this.transicion = {
                        tarjeta,
                        destino,
                        pideMecanico,
                        mecanicoId: tarjeta.mecanico ? String(tarjeta.mecanico.id) : '',
                        observaciones: '',
                        errores: [],
                        enviando: false,
                    };
                },

                cancelarTransicion() {
                    if (! this.transicion || this.transicion.enviando) return;
                    this.transicion = null;
                    this.render(); // la tarjeta vuelve a su columna
                },

                async confirmarTransicion() {
                    const t = this.transicion;
                    t.errores = [];
                    t.enviando = true;

                    const cuerpo = { estado: t.destino, observaciones: t.observaciones || null };
                    if (t.pideMecanico) cuerpo.mecanico_id = t.mecanicoId ? Number(t.mecanicoId) : null;

                    const { ok, status, datos } = await Servimotos.pedir(Servimotos.ruta(this.rutaEstado, t.tarjeta.id), 'PATCH', cuerpo);
                    t.enviando = false;

                    if (ok) {
                        this.transicion = null;
                        this.guardar(datos.orden);
                        this.render();
                        return;
                    }

                    // Error de un campo del modal: se corrige ahí mismo.
                    if (status === 422 && datos.errors) {
                        t.errores = Servimotos.errores(datos);
                        return;
                    }

                    // Regla de negocio (capacidad, estado bloqueado…) o red: la tarjeta vuelve.
                    this.transicion = null;
                    this.render();
                    this.avisar(datos.message ?? 'No se pudo mover la orden.', 'error');
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
