{{-- Utilidades JS compartidas por el Kanban, el detalle de la orden y sus modales. --}}
@once
    <script>
        window.Servimotos = window.Servimotos ?? {
            /** fetch JSON con CSRF. Nunca lanza: devuelve { ok, status, datos }. */
            async pedir(url, metodo, cuerpo = null) {
                try {
                    const respuesta = await fetch(url, {
                        method: metodo,
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: cuerpo ? JSON.stringify(cuerpo) : null,
                    });
                    const datos = await respuesta.json().catch(() => ({}));

                    if (respuesta.status === 419) {
                        datos.message = 'La sesión expiró. Recarga la página.';
                    }

                    return { ok: respuesta.ok, status: respuesta.status, datos };
                } catch (e) {
                    return { ok: false, status: 0, datos: { message: 'No se pudo conectar con el servidor. Intenta de nuevo.' } };
                }
            },

            /** Mensajes de un 422 de Laravel (o el message general) como lista. */
            errores(datos) {
                const lista = Object.values(datos.errors ?? {}).flat();
                return lista.length ? lista : [datos.message ?? 'Ocurrió un error. Intenta de nuevo.'];
            },

            pesos(valor) {
                return '$' + Math.round(Number(valor) || 0).toLocaleString('es-CO');
            },

            esc(texto) {
                return String(texto ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
            },

            /** Rutas generadas en Blade con el marcador __ID__. */
            ruta(plantilla, id) {
                return plantilla.replace('__ID__', encodeURIComponent(id));
            },

            /**
             * Canal entre pestañas del mismo navegador (sin WebSockets). Toda
             * 'orden-actualizada' de esta pestaña se reenvía a las demás: el
             * Kanban actualiza la tarjeta y el dashboard vuelve a consultar.
             */
            canal: 'BroadcastChannel' in window ? new BroadcastChannel('servimotos-ordenes') : null,

            alRecibirDeOtraPestana(callback) {
                this.canal?.addEventListener('message', (evento) => {
                    if (evento.data?.tipo === 'orden-actualizada') callback(evento.data);
                });
            },
        };

        window.addEventListener('orden-actualizada', (evento) => {
            Servimotos.canal?.postMessage({ tipo: 'orden-actualizada', orden: evento.detail?.orden ?? null });
        });
    </script>
@endonce
