<span class="rounded-full px-2 py-0.5 text-xs font-medium {{ match($estado) {
    'activo' => 'bg-green-100 text-green-700',
    'hibernando' => 'bg-yellow-100 text-yellow-700',
    default => 'bg-gray-200 text-gray-600',
} }}">{{ ucfirst($estado) }}</span>
