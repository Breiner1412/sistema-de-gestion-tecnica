@props(['etiqueta', 'valor', 'maximo', 'color' => 'bg-acento'])

{{-- Fila de un gráfico de barras horizontal: rótulo, cifra y barra de 6 px. --}}
@php($ancho = $maximo > 0 ? round($valor / $maximo * 100) : 0)

<div {{ $attributes->merge(['class' => 'mb-2.5 last:mb-0']) }}>
    <div class="flex justify-between gap-3 text-sm">
        <span class="truncate text-tinta-2">{{ $etiqueta }}</span>
        <span class="font-mono font-medium">{{ $valor }}</span>
    </div>
    <div class="mt-1 h-1.5 rounded-full bg-elevada" aria-hidden="true">
        <div class="h-1.5 rounded-full {{ $color }}" style="width: {{ $ancho }}%"></div>
    </div>
</div>
