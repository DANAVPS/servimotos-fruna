{{--
    Modal "Marcar como pagada" (solo Administradora / SuperAdmin; el servidor
    lo vuelve a verificar). Se abre con el evento 'abrir-pago' (detail = tarjeta)
    y al confirmar emite 'orden-actualizada'.

    Regla 3: el método de pago es un dato interno; no se muestra al cliente.
--}}
@include('partials.js-ordenes')

<div x-data="pagoModal(@js(route('ordenes.pagar', ['orden' => '__ID__'])))"
     @abrir-pago.window="abrir($event.detail)"
     @keydown.escape.window="cerrar()">
    <div x-show="abierto" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="titulo-pago">
        <div class="absolute inset-0 bg-gray-900/50" @click="cerrar()"></div>

        <form class="relative w-full max-w-sm rounded-lg bg-white p-6 shadow-xl" @submit.prevent="pagar()">
            <h3 id="titulo-pago" class="text-lg font-semibold text-gray-900">Marcar como pagada</h3>
            <p class="mt-1 text-sm text-gray-600">
                <span x-text="orden?.placa"></span> · <span x-text="orden?.cliente"></span>
            </p>
            <p class="mt-3 text-2xl font-bold text-gray-900" x-text="Servimotos.pesos(orden?.total)"></p>
            <p class="text-xs text-gray-500" x-show="orden?.numero_factura" x-text="`Factura N.º ${orden?.numero_factura}`"></p>

            <fieldset class="mt-4">
                <legend class="text-sm font-medium text-gray-700">¿Cómo pagó el cliente?</legend>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    @foreach(App\Models\OrdenServicio::METODOS_PAGO as $valor => $etiqueta)
                        <label class="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm"
                               :class="metodo === @js($valor) ? 'border-indigo-500 bg-indigo-50 text-indigo-900' : 'border-gray-300'">
                            <input type="radio" name="metodo_pago" value="{{ $valor }}" x-model="metodo" class="text-indigo-600">
                            {{ $etiqueta }}
                        </label>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-gray-500">Dato interno: no aparece en la factura del cliente.</p>
            </fieldset>

            <p x-show="error" x-text="error" x-cloak class="mt-3 text-sm text-red-600" role="alert"></p>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" @click="cerrar()" class="rounded-md border px-4 py-2 text-sm">Cancelar</button>
                <button type="submit" :disabled="enviando || ! metodo"
                        class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-500 disabled:opacity-40">
                    <span x-show="! enviando">Confirmar pago</span>
                    <span x-show="enviando" x-cloak>Guardando…</span>
                </button>
            </div>
        </form>
    </div>
</div>

@once
    <script>
        function pagoModal(rutaPagar) {
            return {
                abierto: false,
                orden: null,
                metodo: '',
                error: null,
                enviando: false,

                abrir(tarjeta) {
                    this.orden = tarjeta;
                    this.metodo = '';
                    this.error = null;
                    this.abierto = true;
                },

                cerrar() {
                    if (! this.enviando) this.abierto = false;
                },

                async pagar() {
                    this.enviando = true;
                    const { ok, datos } = await Servimotos.pedir(Servimotos.ruta(rutaPagar, this.orden.id), 'PATCH', { metodo_pago: this.metodo });
                    this.enviando = false;

                    if (! ok) {
                        this.error = Servimotos.errores(datos).join(' ');
                        return;
                    }

                    this.abierto = false;
                    window.dispatchEvent(new CustomEvent('orden-actualizada', { detail: { orden: datos.orden, mensaje: datos.message } }));
                },
            };
        }
    </script>
@endonce
