<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">Nuevo Repuesto</h2></x-slot>
    <div class="py-6">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('inventario.store') }}">
                    @include('inventario._form')
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
