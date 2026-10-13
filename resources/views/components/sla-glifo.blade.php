@props(['estado', 'tamano' => 12, 'invertido' => false])

{{--
    La forma del semáforo: cada estado tiene la suya para que se lea sin color.
    ok = círculo, atencion = triángulo, riesgo = rombo con "!", vencido =
    cuadrado con "x", pausado = dos barras. `invertido` dibuja el detalle
    interior del color del fondo (para el chip sólido de vencido).
--}}
@php
    $detalle = $invertido ? 'rgb(var(--c-sla-vencido))' : 'rgb(var(--c-superficie))';
@endphp

<svg width="{{ $tamano }}" height="{{ $tamano }}" viewBox="0 0 14 14" aria-hidden="true" {{ $attributes->merge(['class' => 'shrink-0']) }}>
    @switch($estado)
        @case('vencido')
            <rect x="1" y="1" width="12" height="12" rx="2" fill="currentColor"></rect>
            <path d="M4.6 4.6l4.8 4.8m0-4.8-4.8 4.8" stroke="{{ $detalle }}" stroke-width="1.7" stroke-linecap="round"></path>
            @break
        @case('riesgo')
            <path d="M7 .6 13.4 7 7 13.4.6 7Z" fill="currentColor"></path>
            <path d="M7 4v3.4" stroke="rgb(var(--c-superficie))" stroke-width="1.6" stroke-linecap="round"></path>
            <circle cx="7" cy="9.8" r=".9" fill="rgb(var(--c-superficie))"></circle>
            @break
        @case('atencion')
            <path d="M7 1.2 13.4 12.6H.6Z" fill="currentColor"></path>
            @break
        @case('pausado')
            <rect x="2.5" y="1.5" width="3" height="11" rx="1" fill="currentColor"></rect>
            <rect x="8.5" y="1.5" width="3" height="11" rx="1" fill="currentColor"></rect>
            @break
        @default
            <circle cx="7" cy="7" r="6" fill="currentColor"></circle>
    @endswitch
</svg>
