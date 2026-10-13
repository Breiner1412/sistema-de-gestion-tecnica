{{-- Botón de envío principal de los formularios (Breeze). Mismo aspecto que <x-boton variante="primario">. --}}
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex h-9 items-center justify-center gap-2 whitespace-nowrap rounded-control bg-acento px-3.5 text-sm font-semibold text-white transition-colors hover:bg-acento-hover disabled:cursor-not-allowed disabled:opacity-50']) }}>
    {{ $slot }}
</button>
