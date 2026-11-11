<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,600;0,700;1,700;1,800&family=Barlow:wght@400;500;600;700&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="flex min-h-screen flex-wrap bg-white">

            <!-- Carrusel de presentación -->
            <section aria-label="Presentación del taller"
                     x-data="{
                        slide: 0,
                        total: 3,
                        timer: null,
                        ir(i) { this.slide = (i + this.total) % this.total; this.reiniciar(); },
                        reiniciar() {
                            clearInterval(this.timer);
                            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                            this.timer = setInterval(() => this.slide = (this.slide + 1) % this.total, 5000);
                        },
                     }"
                     x-init="reiniciar()"
                     class="relative hidden min-h-[600px] flex-[1.4_1_560px] flex-col justify-between gap-8 overflow-hidden bg-navy-800 px-10 py-11 text-white md:flex xl:px-14">

                <div aria-hidden="true" class="pointer-events-none absolute -bottom-16 -left-5 select-none whitespace-nowrap font-display text-[300px] font-extrabold uppercase italic leading-none text-white/[0.04]">FRUNA</div>
                <div aria-hidden="true" class="absolute -right-20 top-[70px] h-[22px] w-[420px] -skew-x-[35deg] bg-brand-500"></div>
                <div aria-hidden="true" class="absolute -right-20 top-[104px] h-[22px] w-[300px] -skew-x-[35deg] bg-brand-500/55"></div>
                <div aria-hidden="true" class="absolute -right-20 top-[138px] h-[22px] w-[190px] -skew-x-[35deg] bg-plata/35"></div>

                <div class="relative flex items-center gap-3 text-[13px] font-semibold tracking-[0.16em] text-plata">
                    <span class="inline-block h-1 w-7 bg-brand-500"></span>
                    SISTEMA DE GESTIÓN DE TALLER
                </div>

                <div class="relative max-w-2xl" aria-live="polite">
                    <!-- Diapositiva 1: moto -->
                    <div x-show="slide === 0" class="slide-entrada flex flex-col gap-7">
                        <svg viewBox="0 0 560 320" class="block w-full max-w-[520px]" aria-hidden="true" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 150h70M0 182h56M30 118h44" stroke="#F42334" stroke-width="6"/>
                            <circle cx="120" cy="230" r="70" stroke="#fff" stroke-width="10"/>
                            <circle cx="120" cy="230" r="44" stroke="#C3BDBD" stroke-width="3" stroke-dasharray="6 10"/>
                            <circle cx="120" cy="230" r="12" fill="#F42334"/>
                            <circle cx="440" cy="230" r="70" stroke="#fff" stroke-width="10"/>
                            <circle cx="440" cy="230" r="44" stroke="#C3BDBD" stroke-width="3" stroke-dasharray="6 10"/>
                            <circle cx="440" cy="230" r="12" fill="#F42334"/>
                            <path d="M120 230L215 168H300" stroke="#fff" stroke-width="8"/>
                            <path d="M440 230L392 104" stroke="#fff" stroke-width="9"/>
                            <path d="M372 92L414 84" stroke="#fff" stroke-width="8"/>
                            <path d="M200 128Q250 86 330 100L356 132L300 150H214Z" fill="#F42334" stroke="#F42334" stroke-width="4"/>
                            <path d="M140 118L214 112L222 132L150 140Z" fill="#C3BDBD" stroke="#C3BDBD" stroke-width="4"/>
                            <path d="M356 132Q396 112 420 136L398 176L350 164Z" fill="#fff"/>
                            <rect x="226" y="160" width="96" height="56" rx="10" stroke="#fff" stroke-width="7"/>
                            <path d="M238 178h72M238 196h72" stroke="#C3BDBD" stroke-width="4"/>
                            <path d="M226 214L150 238H96" stroke="#C3BDBD" stroke-width="9"/>
                            <path d="M20 304H540" stroke="#fff" stroke-opacity=".25" stroke-width="3"/>
                        </svg>
                        <h2 class="font-display text-6xl font-extrabold uppercase italic leading-[.95]">Tu moto en <span class="text-brand-500">manos expertas</span></h2>
                        <p class="max-w-lg text-lg leading-relaxed text-[#D9D4D4]">Diagnóstico, reparación y mantenimiento con trazabilidad completa de cada orden de servicio.</p>
                    </div>

                    <!-- Diapositiva 2: engranajes -->
                    <div x-show="slide === 1" x-cloak class="slide-entrada flex flex-col gap-7">
                        <svg viewBox="0 0 560 320" class="block w-full max-w-[520px]" aria-hidden="true" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="240" cy="160" r="110" stroke="#fff" stroke-width="34" stroke-dasharray="30 27.6" stroke-linecap="butt"/>
                            <circle cx="240" cy="160" r="88" fill="#1C3242" stroke="#fff" stroke-width="10"/>
                            <circle cx="240" cy="160" r="34" stroke="#F42334" stroke-width="12"/>
                            <circle cx="420" cy="236" r="52" stroke="#C3BDBD" stroke-width="22" stroke-dasharray="20 20.8" stroke-linecap="butt"/>
                            <circle cx="420" cy="236" r="40" fill="#1C3242" stroke="#C3BDBD" stroke-width="6"/>
                            <circle cx="420" cy="236" r="12" fill="#F42334"/>
                            <path d="M440 40L520 120" stroke="#F42334" stroke-width="20"/>
                            <path d="M424 46a26 26 0 1 1 22-22l-14 4-6 6z" stroke="#F42334" stroke-width="12"/>
                            <path d="M20 304H540" stroke="#fff" stroke-opacity=".25" stroke-width="3"/>
                        </svg>
                        <h2 class="font-display text-6xl font-extrabold uppercase italic leading-[.95]">Repuestos <span class="text-brand-500">bajo control</span></h2>
                        <p class="max-w-lg text-lg leading-relaxed text-[#D9D4D4]">Inventario con stock mínimo, ubicación en estante y distribuidores a un mensaje de WhatsApp.</p>
                    </div>

                    <!-- Diapositiva 3: tacómetro -->
                    <div x-show="slide === 2" x-cloak class="slide-entrada flex flex-col gap-7">
                        <svg viewBox="0 0 560 320" class="block w-full max-w-[520px]" aria-hidden="true" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M110 250A170 170 0 0 1 450 250" stroke="#fff" stroke-width="12"/>
                            <path d="M135 250A145 145 0 0 1 425 250" stroke="#C3BDBD" stroke-width="18" stroke-dasharray="4 18" stroke-linecap="butt"/>
                            <path d="M427 165A170 170 0 0 1 450 250" stroke="#F42334" stroke-width="12"/>
                            <path d="M280 250L392 156" stroke="#F42334" stroke-width="10"/>
                            <circle cx="280" cy="250" r="20" fill="#fff"/>
                            <path d="M20 304H540" stroke="#fff" stroke-opacity=".25" stroke-width="3"/>
                        </svg>
                        <h2 class="font-display text-6xl font-extrabold uppercase italic leading-[.95]">Cada mecánico, <span class="text-brand-500">su día</span></h2>
                        <p class="max-w-lg text-lg leading-relaxed text-[#D9D4D4]">Órdenes asignadas, estados en tiempo real y seguimiento con el cliente desde el taller.</p>
                    </div>
                </div>

                <div class="relative flex items-center justify-between gap-4">
                    <div class="flex items-center gap-2.5">
                        <template x-for="i in total" :key="i">
                            <button type="button" @click="ir(i - 1)" :aria-label="'Ver diapositiva ' + i" :aria-current="slide === i - 1"
                                    :class="slide === i - 1 ? 'w-10 bg-brand-500' : 'w-2.5 bg-white/40 hover:bg-white/70'"
                                    class="h-2.5 rounded-full transition-all duration-300"></button>
                        </template>
                    </div>
                    <div class="flex gap-2.5">
                        <button type="button" @click="ir(slide - 1)" aria-label="Anterior" class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/35 hover:bg-white/10">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                        </button>
                        <button type="button" @click="ir(slide + 1)" aria-label="Siguiente" class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/35 hover:bg-white/10">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
                        </button>
                    </div>
                </div>
            </section>

            <!-- Formulario -->
            <section class="flex flex-[1_1_420px] items-center justify-center px-6 py-12 sm:px-8">
                <div class="w-full max-w-[400px]">
                    <a href="/" class="mx-auto mb-8 block w-[280px] max-w-full">
                        <img src="{{ asset('images/logo-servimotos-fruna.png') }}" alt="Servimotos Fruna" class="h-auto w-full">
                    </a>

                    {{ $slot }}

                    <p class="mt-10 text-center text-[13px] text-gray-500">© {{ date('Y') }} Servimotos Fruna · Taller de motos</p>
                </div>
            </section>
        </div>
    </body>
</html>
