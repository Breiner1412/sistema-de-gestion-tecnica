@props(['status'])

@if ($status)
    <div role="status" {{ $attributes->merge(['class' => 'aviso-ok']) }}>
        {{ $status }}
    </div>
@endif
