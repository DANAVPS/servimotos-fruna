<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">Nueva categoría</h2></x-slot>
    <div class="py-6">
        <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('categorias.store') }}">
                    @include('categorias._form')
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
