<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex min-h-[44px] items-center justify-center gap-2 rounded-lg bg-red-700 px-4 text-[15px] font-semibold text-white transition hover:bg-red-800 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2']) }}>
    {{ $slot }}
</button>
