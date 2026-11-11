<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">Nuevo Repuesto</h2></x-slot>
    <div class="py-6">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <form method="POST" action="{{ route('inventario.store') }}">
                    @include('inventario._form')
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
