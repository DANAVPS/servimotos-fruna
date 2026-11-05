@php
    $usuario = auth()->user();
    $alertas = app(\App\Services\InventarioService::class)->repuestosConAlerta()->count();

    $iconos = [
        'dashboard' => 'M3 3h7v9H3zM14 3h7v5h-7zM14 12h7v9h-7zM3 16h7v5H3z',
        'midia' => 'M4 5h16v16H4zM4 10h16M9 3v4M15 3v4',
        'ordenes' => 'M9 3h6v3H9zM7 4.5H5V21h14V4.5h-2M8 11h8M8 15h8M8 19h5',
        'inventario' => 'M3 7l9-4 9 4v10l-9 4-9-4zM3 7l9 4 9-4M12 11v10',
        'clientes' => 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM2 21v-1a6 6 0 0 1 12 0v1M16 3.5a4 4 0 0 1 0 7M18 14a5 5 0 0 1 4 5v2',
        'tipos' => 'M12 3l9 5-9 5-9-5zM3 13l9 5 9-5',
        'mecanicos' => 'M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.5-.5-.5-2.5z',
        'perfil' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21v-1a7 7 0 0 1 14 0v1',
        'salir' => 'M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l-5-5 5-5M5 12h11',
    ];

    $grupos = [
        'Operación' => [
            ['label' => 'Dashboard', 'ruta' => 'dashboard', 'activo' => request()->routeIs('dashboard'), 'icono' => 'dashboard', 'visible' => ! $usuario->esMecanico()],
            ['label' => 'Mi día', 'ruta' => 'mi-dia', 'activo' => request()->routeIs('mi-dia'), 'icono' => 'midia', 'visible' => $usuario->esMecanico()],
            ['label' => 'Órdenes', 'ruta' => 'ordenes.kanban', 'activo' => request()->routeIs('ordenes.*', 'facturas.*'), 'icono' => 'ordenes', 'visible' => true],
        ],
        'Inventario' => [
            ['label' => 'Inventario', 'ruta' => 'inventario.index', 'activo' => request()->routeIs('inventario.*', 'categorias.*', 'distribuidores.*'), 'icono' => 'inventario', 'visible' => true, 'alertas' => $alertas],
        ],
        'Clientes' => [
            ['label' => 'Clientes', 'ruta' => 'clientes.index', 'activo' => request()->routeIs('clientes.*'), 'icono' => 'clientes', 'visible' => $usuario->can('viewAny', App\Models\Cliente::class)],
            ['label' => 'Tipos de Cliente', 'ruta' => 'tipos-cliente.index', 'activo' => request()->routeIs('tipos-cliente.*'), 'icono' => 'tipos', 'visible' => $usuario->can('viewAny', App\Models\TipoCliente::class)],
        ],
        'Equipo' => [
            ['label' => 'Mecánicos', 'ruta' => 'mecanicos.index', 'activo' => request()->routeIs('mecanicos.*'), 'icono' => 'mecanicos', 'visible' => $usuario->can('viewAny', App\Models\Mecanico::class)],
        ],
    ];

    $iniciales = collect(preg_split('/\s+/', trim($usuario->name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    $rolNombre = $usuario->rol?->nombre ?? ($usuario->esMecanico() ? 'Mecánico' : 'Administración');
@endphp

<!-- Barra superior móvil -->
<div class="sticky top-0 z-30 flex items-center justify-between gap-3 bg-navy-800 px-4 py-2 lg:hidden">
    <a href="{{ route($usuario->esMecanico() ? 'mi-dia' : 'dashboard') }}" class="rounded-lg bg-white px-2 py-1">
        <img src="{{ asset('images/logo-servimotos-fruna.png') }}" alt="Servimotos Fruna" class="h-9 w-auto">
    </a>
    <button type="button" @click="menuAbierto = ! menuAbierto" :aria-expanded="menuAbierto.toString()" aria-controls="menu-lateral"
            class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-white hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
        <span class="sr-only">Abrir menú</span>
        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true">
            <path x-show="! menuAbierto" d="M4 6h16M4 12h16M4 18h16" />
            <path x-show="menuAbierto" x-cloak d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>
</div>

<!-- Fondo oscuro del menú en móvil -->
<div x-show="menuAbierto" x-cloak x-transition.opacity @click="menuAbierto = false" class="fixed inset-0 z-30 bg-navy-950/60 lg:hidden"></div>

<!-- Menú lateral -->
<nav id="menu-lateral" aria-label="Menú principal"
     :class="menuAbierto ? 'translate-x-0' : '-translate-x-full'"
     @keydown.escape.window="menuAbierto = false"
     class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col gap-5 overflow-y-auto bg-navy-800 px-4 pb-5 pt-5 text-white transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 lg:shrink-0">

    <a href="{{ route($usuario->esMecanico() ? 'mi-dia' : 'dashboard') }}" class="flex justify-center rounded-xl bg-white px-3 py-2">
        <img src="{{ asset('images/logo-servimotos-fruna.png') }}" alt="Servimotos Fruna" class="h-auto w-44">
    </a>

    <div class="flex items-center gap-2 px-3.5 text-xs font-semibold uppercase tracking-[0.14em] text-plata">
        <span class="franja-roja h-4 w-2" aria-hidden="true"></span>
        {{ $usuario->esMecanico() ? 'Panel mecánico' : 'Panel administrador' }}
    </div>

    @foreach($grupos as $grupo => $items)
        @php($visibles = array_filter($items, fn ($i) => $i['visible']))
        @if(count($visibles))
            <div class="flex flex-col gap-0.5">
                <div class="nav-lateral-grupo">{{ $grupo }}</div>
                @foreach($visibles as $item)
                    <a href="{{ route($item['ruta']) }}" @class(['nav-lateral-item', 'activo' => $item['activo']]) @if($item['activo']) aria-current="page" @endif>
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $iconos[$item['icono']] }}" /></svg>
                        <span class="flex-1">{{ $item['label'] }}</span>
                        @if(($item['alertas'] ?? 0) > 0)
                            <span class="inline-flex min-w-[1.5rem] items-center justify-center rounded-full bg-brand-500 px-2 py-0.5 text-xs font-bold text-white" title="Repuestos con stock bajo">{{ $item['alertas'] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    @endforeach

    <div class="mt-auto flex flex-col gap-1 rounded-xl bg-white/5 p-3">
        <div class="flex items-center gap-3 px-1 pb-2">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-600 text-[15px] font-bold" aria-hidden="true">{{ $iniciales }}</span>
            <div class="min-w-0">
                <div class="truncate text-[15px] font-semibold">{{ $usuario->name }}</div>
                <div class="truncate text-[13px] text-plata">{{ $rolNombre }}</div>
            </div>
        </div>
        <a href="{{ route('profile.edit') }}" @class(['nav-lateral-item', 'activo' => request()->routeIs('profile.*')])>
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $iconos['perfil'] }}" /></svg>
            Mi perfil
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="nav-lateral-item w-full text-left">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $iconos['salir'] }}" /></svg>
                Cerrar sesión
            </button>
        </form>
    </div>
</nav>
