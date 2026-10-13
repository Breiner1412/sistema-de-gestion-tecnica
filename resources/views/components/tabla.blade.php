@props(['minimo' => '720px', 'pie' => null])

{{--
    Tabla densa para uso de horas: filas de 44 px, texto de 13 px, separadores
    finos y sin cebra. En pantallas angostas se desplaza dentro de su caja en
    vez de romper la página. Los estilos de th/td viven en .tabla (app.css).
--}}
<div {{ $attributes->merge(['class' => 'tarjeta overflow-hidden']) }}>
    <div class="overflow-x-auto">
        <table class="tabla" style="min-width: {{ $minimo }}">
            {{ $slot }}
        </table>
    </div>

    @if ($pie)
        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-linea px-3 py-2 text-[13px] text-tinta-3">
            {{ $pie }}
        </div>
    @endif
</div>
