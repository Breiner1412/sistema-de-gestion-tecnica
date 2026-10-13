@props(['variante' => 'secundario', 'tamano' => 'normal', 'href' => null, 'type' => 'button'])

{{--
    Botón de la interfaz. Variantes: primario (la acción del momento, una por
    pantalla), secundario, peligro (cancelar, borrar) y fantasma (enlaces de
    acción). Tamaños: chico (28 px), normal (36 px, escritorio) y campo (56 px,
    a todo el ancho, para el celular). Con `href` se dibuja como enlace.
--}}
@php
    $base = 'inline-flex items-center justify-center gap-2 whitespace-nowrap font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-50';

    $tamanos = [
        'chico' => 'h-7 rounded-control px-2.5 text-xs',
        'normal' => 'h-9 rounded-control px-3.5 text-sm',
        'campo' => 'h-14 w-full rounded-[12px] px-4 text-[17px]',
    ];

    $variantes = [
        'primario' => 'bg-acento text-white hover:bg-acento-hover',
        'secundario' => 'border border-linea bg-superficie font-medium text-tinta hover:bg-elevada',
        'peligro' => 'border border-peligro-borde bg-superficie text-peligro hover:bg-peligro/10',
        'fantasma' => 'text-acento-texto hover:bg-acento-tinte',
    ];

    $clases = $base.' '.($tamanos[$tamano] ?? $tamanos['normal']).' '.($variantes[$variante] ?? $variantes['secundario']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $clases.' no-underline']) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $clases]) }}>{{ $slot }}</button>
@endif
