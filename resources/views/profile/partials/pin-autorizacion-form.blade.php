@php
    $tienePin = $user->tienePinConfigurado();
    $errores = $errors->pinAutorizacion;
@endphp

<section id="pin-autorizacion">
    <header>
        <h2 class="text-lg font-medium text-gray-900">PIN de autorización</h2>
        <p class="mt-1 text-sm text-gray-600">
            Código de 6 dígitos que se pide para editar clientes, cambiar su estado, editar sus motos o reinscribirlos.
            Es personal: no lo compartas con nadie del taller.
        </p>
    </header>

    @if(session('status-pin'))
        <div class="mt-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">{{ session('status-pin') }}</div>
    @endif

    @if(session('status') === 'pin-configurado')
        <div class="mt-4 rounded-md bg-green-50 p-3 text-sm text-green-700">PIN configurado. Ya puedes editar clientes.</div>
    @elseif(session('status') === 'pin-actualizado')
        <div class="mt-4 rounded-md bg-green-50 p-3 text-sm text-green-700">PIN actualizado.</div>
    @endif

    <p class="mt-4 text-sm">
        Estado:
        @if($tienePin)
            <span class="font-medium text-green-700">✓ Configurado</span>
        @else
            <span class="font-medium text-amber-700">Sin configurar</span>
        @endif
    </p>

    <form method="POST" action="{{ route('profile.pin.update') }}" class="mt-6 space-y-6" autocomplete="off">
        @csrf
        @method('PUT')

        @if($tienePin)
            <div>
                <x-input-label for="pin_current_password" value="Contraseña actual" />
                <x-text-input id="pin_current_password" name="current_password" type="password" class="mt-1 block w-full" autocomplete="current-password" />
                <x-input-error :messages="$errores->get('current_password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="pin_actual" value="PIN actual" />
                <x-text-input id="pin_actual" name="pin_actual" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                              class="mt-1 block w-full font-mono tracking-widest" autocomplete="off" />
                <x-input-error :messages="$errores->get('pin_actual')" class="mt-2" />
            </div>
        @endif

        <div>
            <x-input-label for="pin" :value="$tienePin ? 'PIN nuevo' : 'PIN (6 dígitos)'" />
            <x-text-input id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                          class="mt-1 block w-full font-mono tracking-widest" autocomplete="new-password" />
            <x-input-error :messages="$errores->get('pin')" class="mt-2" />
            <p class="mt-1 text-xs text-gray-500">Evita secuencias (123456) o dígitos repetidos (000000).</p>
        </div>

        <div>
            <x-input-label for="pin_confirmation" value="Confirmar PIN" />
            <x-text-input id="pin_confirmation" name="pin_confirmation" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                          class="mt-1 block w-full font-mono tracking-widest" autocomplete="new-password" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ $tienePin ? 'Cambiar PIN' : 'Configurar PIN' }}</x-primary-button>
        </div>
    </form>
</section>
