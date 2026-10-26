{{--
    Modal de PIN de autorización. Uso:

    <form method="POST" action="..." x-data="formularioConPin()" @submit.prevent="pedirPin()">
        @csrf
        @include('partials.pin-modal', ['titulo' => 'Confirmar cambios'])
        ...campos...
    </form>

    Al confirmar, envía el formulario con fetch (Accept: JSON) para que un PIN
    incorrecto (403) se muestre aquí sin perder lo que se escribió. El PIN se
    borra del componente tras cada intento y nunca se guarda en sesión.
--}}
<div x-show="errores.length" x-cloak class="mb-4 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">
    <p class="font-semibold">Revisa estos datos:</p>
    <ul class="mt-1 list-inside list-disc">
        <template x-for="error in errores" :key="error"><li x-text="error"></li></template>
    </ul>
</div>

@error('pin_autorizacion')
    <div class="mb-4 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">{{ $message }}</div>
@enderror

<div x-show="modal" x-cloak @keydown.escape.window="cerrar()"
     class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-gray-900/50" @click="cerrar()"></div>
    <div class="relative w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
        <h3 class="text-lg font-semibold text-gray-900">{{ $titulo ?? 'Autorizar cambios' }}</h3>
        <p class="mt-1 text-sm text-gray-600">Ingresa tu PIN de autorización de 6 dígitos.</p>

        <input type="password" name="pin_autorizacion" x-ref="pin" x-model="pin"
               inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="off"
               @keydown.enter.prevent="confirmar()"
               class="mt-4 block w-full rounded-md border-gray-300 text-center font-mono text-2xl tracking-[0.5em] shadow-sm"
               aria-label="PIN de autorización">

        <p x-show="error" x-text="error" x-cloak class="mt-2 text-sm text-red-600" role="alert"></p>

        <div class="mt-6 flex justify-end gap-3">
            <button type="button" @click="cerrar()" class="rounded-md border px-4 py-2 text-sm">Cancelar</button>
            <button type="button" @click="confirmar()" :disabled="enviando || bloqueado"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-40">
                <span x-show="! enviando">Autorizar</span>
                <span x-show="enviando" x-cloak>Verificando…</span>
            </button>
        </div>
    </div>
</div>

@once
    <script>
        function formularioConPin() {
            return {
                modal: false,
                pin: '',
                error: null,
                errores: [],
                enviando: false,
                bloqueado: false,

                pedirPin() {
                    this.error = null;
                    this.pin = '';
                    this.modal = true;
                    this.$nextTick(() => this.$refs.pin.focus());
                },

                cerrar() {
                    this.modal = false;
                    this.pin = '';
                },

                async confirmar() {
                    if (! /^\d{6}$/.test(this.pin)) {
                        this.error = 'El PIN tiene 6 dígitos.';
                        return;
                    }

                    this.enviando = true;
                    const formulario = this.$root;

                    try {
                        const respuesta = await fetch(formulario.action, {
                            method: 'POST',
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            body: new FormData(formulario),
                        });
                        const datos = await respuesta.json().catch(() => ({}));

                        if (respuesta.ok || respuesta.status === 409) {
                            // Éxito, o falta configurar el PIN: el servidor dice a dónde ir.
                            window.location.href = datos.redirect ?? window.location.href;
                            return;
                        }

                        if (respuesta.status === 403) {
                            this.error = datos.message ?? 'No autorizado.';
                            this.bloqueado = datos.intentos_restantes === 0;
                            return;
                        }

                        if (respuesta.status === 422) {
                            const campos = Object.entries(datos.errors ?? {});
                            const errorPin = campos.find(([campo]) => campo === 'pin_autorizacion');

                            // Regla de negocio sin errores de campo (p. ej. estado de la orden).
                            if (! campos.length) {
                                this.error = datos.message ?? 'No se pudo completar la acción.';
                                return;
                            }

                            if (errorPin && campos.length === 1) {
                                this.error = errorPin[1][0];
                                return;
                            }

                            this.errores = campos.filter(([campo]) => campo !== 'pin_autorizacion').flatMap(([, mensajes]) => mensajes);
                            this.modal = false;
                            formulario.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            return;
                        }

                        if (respuesta.status === 419) {
                            this.error = 'La sesión expiró. Recarga la página (tus cambios se perderán).';
                            return;
                        }

                        this.error = datos.message ?? 'Ocurrió un error. Intenta de nuevo.';
                    } catch (e) {
                        this.error = 'No se pudo conectar con el servidor. Intenta de nuevo.';
                    } finally {
                        this.pin = '';
                        this.enviando = false;
                    }
                },
            };
        }
    </script>
@endonce
