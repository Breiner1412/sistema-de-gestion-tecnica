@props(['titulo', 'subtitulo' => null])

{{-- Encabezado de pantalla: título, una línea de contexto y las acciones a la derecha. --}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-end justify-between gap-3']) }}>
    <div class="flex min-w-0 flex-col gap-0.5">
        @isset($antes)
            <div class="text-[13px] text-tinta-3">{{ $antes }}</div>
        @endisset
        <h1 class="text-[22px] font-semibold leading-tight text-tinta">{{ $titulo }}</h1>
        @if ($subtitulo)
            <p class="text-[13px] text-tinta-3">{{ $subtitulo }}</p>
        @endif
    </div>

    @isset($acciones)
        <div class="flex flex-wrap items-center gap-2">{{ $acciones }}</div>
    @endisset
</div>
