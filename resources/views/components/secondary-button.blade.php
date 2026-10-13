<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex h-9 items-center justify-center gap-2 whitespace-nowrap rounded-control border border-linea bg-superficie px-3.5 text-sm font-medium text-tinta transition-colors hover:bg-elevada disabled:cursor-not-allowed disabled:opacity-50']) }}>
    {{ $slot }}
</button>
