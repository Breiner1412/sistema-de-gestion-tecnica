{{-- Avisos de la sesión ("mensaje" y "error"), con su forma además del color. --}}
@if (session('mensaje'))
    <div role="status" {{ $attributes->merge(['class' => 'aviso-ok flex items-center gap-2']) }}>
        <svg width="16" height="16" viewBox="0 0 10 10" aria-hidden="true"><path d="M1.5 5.2 4 7.6 8.6 2.6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></path></svg>
        <span>{{ session('mensaje') }}</span>
    </div>
@endif

@if (session('error'))
    <div role="alert" {{ $attributes->merge(['class' => 'aviso-error flex items-center gap-2']) }}>
        <svg width="16" height="16" viewBox="0 0 14 14" aria-hidden="true"><path d="M7 1.2 13.4 12.6H.6Z" fill="currentColor"></path></svg>
        <span>{{ session('error') }}</span>
    </div>
@endif
