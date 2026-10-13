@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'control h-10 disabled:bg-elevada disabled:text-tinta-3']) }}>
