<x-guest-layout>
    <div class="mb-6 flex flex-col gap-1.5">
        <h1 class="titulo-display text-4xl leading-none">Bienvenido</h1>
        <p class="text-base text-gray-500">Ingresa con tu cuenta del taller.</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-5">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" class="mt-2 block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="tu@correo.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div x-data="{ ver: false }">
            <x-input-label for="password" value="Contraseña" />
            <div class="relative mt-2">
                <x-text-input id="password" class="block w-full pe-12"
                                x-bind:type="ver ? 'text' : 'password'"
                                type="password"
                                name="password"
                                required autocomplete="current-password" />
                <button type="button" @click="ver = ! ver" :aria-label="ver ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                        class="absolute inset-y-0 end-0 flex w-11 items-center justify-center text-gray-500 hover:text-navy-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <!-- Remember Me -->
            <label for="remember_me" class="inline-flex min-h-[44px] items-center">
                <input id="remember_me" type="checkbox" class="h-[18px] w-[18px] rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500" name="remember">
                <span class="ms-2 text-[15px] text-gray-700">Recordarme</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-[15px] font-semibold text-brand-600 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 rounded" href="{{ route('password.request') }}">
                    ¿Olvidaste tu contraseña?
                </a>
            @endif
        </div>

        <button type="submit" class="btn-primario min-h-[50px] w-full text-base">
            Ingresar
            <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </button>
    </form>
</x-guest-layout>
