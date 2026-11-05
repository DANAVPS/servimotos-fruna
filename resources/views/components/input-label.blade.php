@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-semibold text-navy-700']) }}>
    {{ $value ?? $slot }}
</label>
