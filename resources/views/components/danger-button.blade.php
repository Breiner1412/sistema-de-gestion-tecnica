{{-- Acción destructiva confirmada (por ejemplo, dentro de un modal): relleno sólido. --}}
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex h-9 items-center justify-center gap-2 whitespace-nowrap rounded-control bg-sla-vencido px-3.5 text-sm font-semibold text-white transition-colors hover:bg-sla-vencido-oscuro disabled:cursor-not-allowed disabled:opacity-50']) }}>
    {{ $slot }}
</button>
