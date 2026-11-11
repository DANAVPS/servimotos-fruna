@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'min-h-[46px] rounded-lg border-gray-300 text-[15px] text-navy-700 shadow-sm placeholder:text-gray-400 focus:border-navy-700 focus:ring-navy-700']) }}>
