@props(['active'])

@php
$classes = ($active ?? false)
    ? 'flex h-11 w-full items-center rounded-control bg-menu-activo px-3 text-start text-base font-semibold text-white no-underline'
    : 'flex h-11 w-full items-center rounded-control px-3 text-start text-base text-menu-tinta no-underline hover:bg-menu-activo hover:text-white';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} @if ($active ?? false) aria-current="page" @endif>
    {{ $slot }}
</a>
