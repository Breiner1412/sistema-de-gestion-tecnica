@php
if (! isset($scrollTo)) {
    $scrollTo = 'body';
}

$scrollIntoViewJsSnippet = ($scrollTo !== false)
    ? <<<JS
       (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
    JS
    : '';

// Paginación de la interfaz (dirección Consola): botones de 32 px, la página
// actual marcada con relleno y aria-current, y el conteo en cifras mono.
$casilla = 'inline-flex h-8 min-w-8 items-center justify-center rounded-control border px-2 text-[13px] font-medium transition-colors';
$activa = $casilla.' border-linea bg-superficie text-tinta hover:bg-elevada';
$inactiva = $casilla.' cursor-default border-linea-suave bg-transparent text-tinta-3 opacity-60';
$sufijo = $paginator->getPageName() == 'page' ? '' : '.'.$paginator->getPageName();
@endphp

<div>
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="Paginación" class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-[13px] text-tinta-3">
                <span class="font-mono text-tinta">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span>
                de <span class="font-mono text-tinta">{{ $paginator->total() }}</span>
            </p>

            <div class="flex items-center gap-1">
                @if ($paginator->onFirstPage())
                    <span class="{{ $inactiva }}" aria-disabled="true" aria-label="Página anterior">
                        <svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true"><path d="M10 3 5 8l5 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </span>
                @else
                    <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" dusk="previousPage{{ $sufijo }}.after" class="{{ $activa }}" aria-label="Página anterior">
                        <svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true"><path d="M10 3 5 8l5 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </button>
                @endif

                {{-- En el celular solo las flechas y la página actual. --}}
                <span class="{{ $casilla }} border-transparent font-mono text-tinta sm:hidden">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="hidden px-1 text-tinta-3 sm:inline" aria-disabled="true">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            <span wire:key="paginator-{{ $paginator->getPageName() }}-page{{ $page }}" class="hidden sm:inline-flex">
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page" class="{{ $casilla }} border-menu bg-menu font-mono text-white dark:border-linea dark:bg-elevada dark:text-tinta">{{ $page }}</span>
                                @else
                                    <button type="button" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" class="{{ $activa }} font-mono" aria-label="Ir a la página {{ $page }}">{{ $page }}</button>
                                @endif
                            </span>
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" dusk="nextPage{{ $sufijo }}.after" class="{{ $activa }}" aria-label="Página siguiente">
                        <svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true"><path d="m6 3 5 5-5 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </button>
                @else
                    <span class="{{ $inactiva }}" aria-disabled="true" aria-label="Página siguiente">
                        <svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true"><path d="m6 3 5 5-5 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </span>
                @endif
            </div>
        </nav>
    @endif
</div>
