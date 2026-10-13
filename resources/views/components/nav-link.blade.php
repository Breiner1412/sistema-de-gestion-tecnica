@props(['active'])

{{-- Enlace del menú lateral oscuro. --}}
@php
$classes = ($active ?? false)
    ? 'flex h-9 items-center justify-between gap-2 rounded-control bg-menu-activo px-2.5 text-sm font-semibold text-white no-underline'
    : 'flex h-9 items-center justify-between gap-2 rounded-control px-2.5 text-sm text-menu-tinta no-underline transition-colors hover:bg-menu-activo hover:text-white';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} @if ($active ?? false) aria-current="page" @endif>
    {{ $slot }}
</a>
