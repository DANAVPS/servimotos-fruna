{{--
    "+ Crear orden manual". Se abre con el evento 'abrir-crear-orden' y al
    crear emite 'orden-actualizada' con la tarjeta nueva (siempre en
    "En espera de revisión").

    Cliente existente (buscador de clientes) con una de sus motos o una moto
    nueva, o cliente nuevo + moto. Un cliente nuevo queda SIEMPRE como Regular.
--}}
@include('partials.js-ordenes')

<div x-data="crearOrdenModal(@js(route('ordenes.store')), @js(route('clientes.buscar')))"
     @abrir-crear-orden.window="abrir()"
     @keydown.escape.window="cerrar()">
    <div x-show="abierto" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="titulo-crear-orden">
        <div class="fixed inset-0 bg-gray-900/50" @click="cerrar()"></div>

        <form class="relative my-8 w-full max-w-xl rounded-lg bg-white shadow-xl" @submit.prevent="crear()">
            <div class="border-b px-6 py-4">
                <h3 id="titulo-crear-orden" class="text-lg font-semibold text-gray-900">Nueva orden de servicio</h3>
                <p class="text-sm text-gray-600">La moto entra en "En espera de revisión".</p>
            </div>

            <div class="space-y-4 px-6 py-4">
                <div x-show="errores.length" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert">
                    <ul class="list-inside list-disc">
                        <template x-for="error in errores" :key="error"><li x-text="error"></li></template>
                    </ul>
                </div>

                <div class="flex gap-2 text-sm">
                    <button type="button" @click="modo = 'existente'"
                            :class="modo === 'existente' ? 'bg-indigo-600 text-white' : 'border text-gray-700'"
                            class="rounded-md px-3 py-1.5">Cliente registrado</button>
                    <button type="button" @click="modo = 'nuevo'; cliente = null"
                            :class="modo === 'nuevo' ? 'bg-indigo-600 text-white' : 'border text-gray-700'"
                            class="rounded-md px-3 py-1.5">Cliente nuevo</button>
                </div>

                {{-- Cliente registrado --}}
                <div x-show="modo === 'existente'" class="space-y-3">
                    <template x-if="! cliente">
                        <div class="relative">
                            <label for="buscar-cliente" class="block text-sm font-medium text-gray-700">Buscar cliente</label>
                            <input type="search" id="buscar-cliente" x-model="busqueda" @input.debounce.300ms="buscar()"
                                   placeholder="{{ App\Support\TarjetaOrden::veDinero(auth()->user()) ? 'Nombre, teléfono, cédula/NIT o placa' : 'Nombre o placa' }}" autocomplete="off"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <p x-show="sinResultados" class="mt-1 text-xs text-gray-500">
                                No se encontró. <button type="button" class="text-indigo-600 underline" @click="modo = 'nuevo'">Registrar cliente nuevo</button>
                            </p>
                            <div x-show="resultados.length" class="absolute z-10 mt-1 max-h-60 w-full overflow-y-auto rounded-md border bg-white shadow-lg">
                                <template x-for="c in resultados" :key="c.id">
                                    <button type="button" @click="elegir(c)" class="block w-full px-3 py-2 text-left text-sm hover:bg-indigo-50">
                                        <span class="font-medium text-gray-900" x-text="c.nombre"></span>
                                        <span class="block text-xs text-gray-500"
                                              x-text="[c.cedula_nit, c.telefono, c.motos.map((m) => m.placa).join(', ')].filter(Boolean).join(' · ')"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    <template x-if="cliente">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between rounded-md bg-gray-50 px-3 py-2 text-sm">
                                <span>
                                    <span class="font-medium text-gray-900" x-text="cliente.nombre"></span>
                                    <span class="ml-1 text-xs text-gray-500" x-show="cliente.tipo_cliente" x-text="cliente.tipo_cliente?.nombre"></span>
                                    <span x-show="cliente.estado_cliente === 'hibernando'" class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700">Hibernando · se reactiva</span>
                                </span>
                                <button type="button" @click="cliente = null; motoId = ''" class="text-xs text-indigo-600 underline">Cambiar</button>
                            </div>

                            <fieldset>
                                <legend class="text-sm font-medium text-gray-700">Moto</legend>
                                <div class="mt-1 space-y-1">
                                    <template x-for="m in cliente.motos" :key="m.id">
                                        <label class="flex items-center gap-2 text-sm">
                                            <input type="radio" name="moto_id" :value="String(m.id)" x-model="motoId" class="text-indigo-600">
                                            <span class="font-mono font-medium" x-text="m.placa"></span>
                                            <span class="text-gray-500" x-text="`${m.marca} ${m.modelo}`"></span>
                                        </label>
                                    </template>
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="radio" name="moto_id" value="nueva" x-model="motoId" class="text-indigo-600">
                                        Otra moto (registrarla ahora)
                                    </label>
                                </div>
                            </fieldset>
                        </div>
                    </template>
                </div>

                {{-- Cliente nuevo: siempre Regular --}}
                <div x-show="modo === 'nuevo'" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="nuevo-nombre" class="block text-sm font-medium text-gray-700">Nombre</label>
                        <input type="text" id="nuevo-nombre" x-model="nuevoCliente.nombre" maxlength="255"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <label for="nuevo-telefono" class="block text-sm font-medium text-gray-700">Celular</label>
                        <input type="tel" id="nuevo-telefono" x-model="nuevoCliente.telefono" placeholder="300 123 4567"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <label for="nuevo-cedula" class="block text-sm font-medium text-gray-700">Cédula / NIT <span class="text-gray-400">(opcional)</span></label>
                        <input type="text" id="nuevo-cedula" x-model="nuevoCliente.cedula_nit"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <p class="text-xs text-gray-500 sm:col-span-2">Se registra como cliente Regular. La administradora puede cambiar el tipo después.</p>
                </div>

                {{-- Moto nueva --}}
                <div x-show="necesitaMotoNueva" class="grid grid-cols-1 gap-3 rounded-md border border-dashed p-3 sm:grid-cols-4">
                    <div>
                        <label for="moto-placa" class="block text-sm font-medium text-gray-700">Placa</label>
                        <input type="text" id="moto-placa" x-model="moto.placa" placeholder="ABC12D" maxlength="7"
                               class="mt-1 block w-full rounded-md border-gray-300 font-mono uppercase shadow-sm">
                    </div>
                    <div>
                        <label for="moto-marca" class="block text-sm font-medium text-gray-700">Marca</label>
                        <input type="text" id="moto-marca" x-model="moto.marca" maxlength="100"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <label for="moto-modelo" class="block text-sm font-medium text-gray-700">Modelo</label>
                        <input type="text" id="moto-modelo" x-model="moto.modelo" maxlength="100"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <label for="moto-anio" class="block text-sm font-medium text-gray-700">Año</label>
                        <input type="number" id="moto-anio" x-model="moto.anio" min="1950"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                </div>

                <div>
                    <label for="descripcion-falla" class="block text-sm font-medium text-gray-700">Falla reportada por el cliente</label>
                    <textarea id="descripcion-falla" rows="2" x-model="descripcionFalla" maxlength="1000"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-3 border-t px-6 py-4">
                <button type="button" @click="cerrar()" class="rounded-md border px-4 py-2 text-sm">Cancelar</button>
                <button type="submit" :disabled="enviando || ! listo"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-40">
                    <span x-show="! enviando">Crear orden</span>
                    <span x-show="enviando" x-cloak>Creando…</span>
                </button>
            </div>
        </form>
    </div>
</div>

@once
    <script>
        function crearOrdenModal(rutaCrear, rutaBuscarClientes) {
            const vacio = () => ({
                modo: 'existente',
                busqueda: '',
                resultados: [],
                sinResultados: false,
                cliente: null,
                motoId: '',
                nuevoCliente: { nombre: '', telefono: '', cedula_nit: '' },
                moto: { placa: '', marca: '', modelo: '', anio: '' },
                descripcionFalla: '',
                errores: [],
            });

            return {
                abierto: false,
                enviando: false,
                ...vacio(),

                abrir() {
                    Object.assign(this, vacio());
                    this.abierto = true;
                    this.$nextTick(() => document.getElementById('buscar-cliente')?.focus());
                },

                cerrar() {
                    if (! this.enviando) this.abierto = false;
                },

                get necesitaMotoNueva() {
                    return this.modo === 'nuevo' || (this.cliente !== null && this.motoId === 'nueva');
                },

                get listo() {
                    return this.modo === 'nuevo' || (this.cliente !== null && this.motoId !== '');
                },

                async buscar() {
                    const q = this.busqueda.trim();
                    this.sinResultados = false;

                    if (q.length < 2) {
                        this.resultados = [];
                        return;
                    }

                    const { ok, datos } = await Servimotos.pedir(`${rutaBuscarClientes}?q=${encodeURIComponent(q)}`, 'GET');

                    if (ok && q === this.busqueda.trim()) {
                        this.resultados = datos.data ?? [];
                        this.sinResultados = this.resultados.length === 0;
                    }
                },

                elegir(cliente) {
                    this.cliente = cliente;
                    this.resultados = [];
                    // Con una sola moto, queda elegida; sin motos, se registra una.
                    this.motoId = cliente.motos.length === 1 ? String(cliente.motos[0].id) : (cliente.motos.length ? '' : 'nueva');
                },

                async crear() {
                    this.errores = [];
                    const cuerpo = { descripcion_falla: this.descripcionFalla || null };

                    if (this.modo === 'nuevo') {
                        cuerpo.cliente = { ...this.nuevoCliente };
                    } else {
                        cuerpo.cliente_id = this.cliente.id;
                    }

                    if (this.necesitaMotoNueva) {
                        cuerpo.moto = { ...this.moto, anio: this.moto.anio || null };
                    } else {
                        cuerpo.moto_id = Number(this.motoId);
                    }

                    this.enviando = true;
                    const { ok, datos } = await Servimotos.pedir(rutaCrear, 'POST', cuerpo);
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
