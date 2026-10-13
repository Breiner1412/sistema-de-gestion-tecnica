<?php

use App\Models\InformeMensual;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public InformeMensual $informe;

    public string $observaciones = '';
    public string $compromisos = '';

    public function mount(InformeMensual $informe): void
    {
        $usuario = auth()->user();

        // El técnico puede leer el suyo; la gestión puede leer y escribir todos.
        $this->authorize('view', $informe);

        $this->informe = $informe->load('tecnico', 'generadoPor');
        $this->observaciones = (string) $informe->observaciones;
        $this->compromisos = (string) $informe->compromisos;

        // Que el técnico lo abra deja constancia de que lo recibió.
        if ($usuario->id === $informe->tecnico_id && $informe->estaPublicado() && !$informe->visto_at) {
            $informe->update(['visto_at' => now()]);
        }
    }

    #[Computed]
    public function puedeEditar(): bool
    {
        return auth()->user()->can('update', $this->informe);
    }

    #[Computed]
    public function cifras(): array
    {
        return $this->informe->cifras();
    }

    public function guardar(): void
    {
        $this->authorize('update', $this->informe);

        $this->validate([
            'observaciones' => ['nullable', 'string', 'max:4000'],
            'compromisos' => ['nullable', 'string', 'max:4000'],
        ]);

        $this->informe->update([
            'observaciones' => $this->observaciones ?: null,
            'compromisos' => $this->compromisos ?: null,
        ]);

        session()->flash('mensaje', 'Borrador guardado.');
    }

    public function publicar(): void
    {
        $this->authorize('update', $this->informe);

        if (blank($this->observaciones)) {
            $this->addError('observaciones', 'Un informe sin observaciones no le sirve a nadie: escribe la retroalimentación antes de publicar.');

            return;
        }

        $this->guardar();
        $this->informe->publicar();
        $this->informe->refresh();

        unset($this->cifras, $this->puedeEditar);

        session()->flash('mensaje', 'Informe publicado. Las cifras quedaron congeladas.');
    }
}; ?>

{{-- El informe se imprime siempre en claro: app.css devuelve los tokens claros
     en @media print aunque la pantalla esté en oscuro. --}}
<div class="mx-auto flex max-w-[1000px] flex-col gap-4 px-4 py-5 sm:px-6 lg:py-6 print:max-w-none print:p-0">
    <x-mensajes class="no-imprimir" />

    <div class="no-imprimir flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('informes.index') }}" wire:navigate class="inline-flex items-center gap-1 text-[13px] text-tinta-3 no-underline hover:text-acento-texto">
            <svg width="13" height="13" viewBox="0 0 16 16" aria-hidden="true"><path d="M10 3 5 8l5 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></path></svg>
            Informes
        </a>

        <div class="flex flex-wrap gap-2">
            <x-boton onclick="window.print()">
                <svg width="15" height="15" viewBox="0 0 16 16" aria-hidden="true"><path d="M4.5 6V2.5h7V6M4.5 11.5h-2v-5h11v5h-2M4.5 9.5h7v4h-7Z" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"></path></svg>
                Imprimir
            </x-boton>

            @if ($this->puedeEditar)
                <x-boton wire:click="guardar">Guardar borrador</x-boton>
                <x-boton variante="primario" wire:click="publicar" wire:confirm="Al publicar, las cifras quedan congeladas y el informe ya no se puede editar. ¿Continuar?">Publicar</x-boton>
            @endif
        </div>
    </div>

    @php $c = $this->cifras; @endphp

    <article class="tarjeta space-y-6 p-5 sm:p-8 print:border-0 print:p-0">

        {{-- Encabezado --}}
        <header class="flex flex-wrap items-start justify-between gap-4 border-b border-linea pb-5">
            <div>
                <p class="titulo-seccion">Informe de rendimiento</p>
                <h1 class="mt-1 text-[26px] font-semibold leading-tight">{{ $informe->tecnico->name }}</h1>
                <p class="text-tinta-2">{{ $informe->periodo() }}</p>
            </div>
            <div class="text-right">
                <p class="titulo-seccion">Casos atendidos</p>
                <p class="mt-1 font-mono text-[40px] font-semibold leading-none">{{ $c['total'] }}</p>
                @if ($informe->estaPublicado())
                    <p class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-sla-ok">
                        <svg width="10" height="10" viewBox="0 0 10 10" aria-hidden="true"><path d="M1.5 5.2 4 7.6 8.6 2.6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                        Publicado <span class="font-mono">{{ $informe->publicado_at->format('d/m/Y') }}</span>
                    </p>
                @else
                    <p class="mt-2 inline-flex h-[22px] items-center rounded-full border border-dashed border-tinta-3 px-2 text-xs font-medium text-tinta-2">Borrador</p>
                @endif
            </div>
        </header>

        @if ($c['total'] === 0)
            <p class="text-tinta-3">Este técnico no tiene casos registrados en el período.</p>
        @else
            {{-- Indicadores --}}
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 print:grid-cols-4">
                @php
                    $tarjetas = [
                        ['Resueltos', $c['resueltos'], $c['tasa_resolucion'] !== null ? $c['tasa_resolucion'].'% del total' : ''],
                        ['Sin contacto', $c['sin_contacto'], $c['tasa_sin_contacto'] !== null ? $c['tasa_sin_contacto'].'% del total' : ''],
                        ['Escalados a redes', $c['escalados'], 'enviados a nivel 3'],
                        ['Enviados a terreno', $c['enviados_campo'], 'requirieron visita'],
                    ];
                @endphp

                @foreach ($tarjetas as [$titulo, $valor, $pie])
                    <div class="rounded-tarjeta border border-linea p-3">
                        <p class="text-xs font-medium text-tinta-3">{{ $titulo }}</p>
                        <p class="mt-1 font-mono text-[26px] font-semibold leading-none">{{ $valor }}</p>
                        <p class="mt-1 text-xs text-tinta-3">{{ $pie }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Ritmo y comparación --}}
            @php
                $fila = 'flex justify-between gap-3 border-b border-linea-suave py-1.5 last:border-b-0';
                $cifra = 'font-mono font-medium text-tinta';
            @endphp
            <div class="grid gap-4 md:grid-cols-2 print:grid-cols-2">
                <section class="rounded-tarjeta border border-linea p-4">
                    <h2 class="titulo-seccion mb-2">Ritmo de trabajo</h2>
                    <dl class="text-sm text-tinta-2">
                        <div class="{{ $fila }}"><dt>Días con actividad</dt><dd class="{{ $cifra }}">{{ $c['dias_trabajados'] }}</dd></div>
                        <div class="{{ $fila }}"><dt>Promedio por día activo</dt><dd class="{{ $cifra }}">{{ $c['promedio_diario'] ?? '—' }}</dd></div>
                        <div class="{{ $fila }}"><dt>Día más cargado</dt><dd class="{{ $cifra }}">{{ $c['mejor_dia'] ? 'día '.$c['mejor_dia'] : '—' }}</dd></div>
                        <div class="{{ $fila }}">
                            <dt>Tiempo medio de resolución</dt>
                            <dd class="{{ $cifra }}">{{ $c['promedio_resolucion'] !== null ? $c['promedio_resolucion'].' min hábiles' : 'sin datos' }}</dd>
                        </div>
                        <div class="{{ $fila }}"><dt>Casos inmediatos atendidos</dt><dd class="{{ $cifra }}">{{ $c['inmediatos'] }}</dd></div>
                    </dl>
                </section>

                <section class="rounded-tarjeta border border-linea p-4">
                    <h2 class="titulo-seccion mb-2">Frente al equipo</h2>
                    <dl class="text-sm text-tinta-2">
                        <div class="{{ $fila }}"><dt>Técnicos activos en el mes</dt><dd class="{{ $cifra }}">{{ $c['equipo']['tecnicos_activos'] }}</dd></div>
                        <div class="{{ $fila }}"><dt>Promedio de casos del equipo</dt><dd class="{{ $cifra }}">{{ $c['equipo']['promedio_casos'] ?? '—' }}</dd></div>
                        <div class="{{ $fila }}"><dt>Participación en la carga</dt><dd class="{{ $cifra }}">{{ $c['equipo']['participacion'] !== null ? $c['equipo']['participacion'].'%' : '—' }}</dd></div>
                        <div class="{{ $fila }}">
                            <dt>Posición por volumen</dt>
                            <dd class="{{ $cifra }}">{{ $c['equipo']['posicion'] ? $c['equipo']['posicion'].' de '.$c['equipo']['tecnicos_activos'] : '—' }}</dd>
                        </div>
                        <div class="{{ $fila }}">
                            <dt>Resolución media del equipo</dt>
                            <dd class="{{ $cifra }}">{{ $c['equipo']['promedio_resolucion'] !== null ? $c['equipo']['promedio_resolucion'].' min' : 'sin datos' }}</dd>
                        </div>
                    </dl>
                    <p class="mt-2 text-xs text-tinta-3">
                        El volumen no es calidad: sirve para leer la carga, no para rankear personas.
                    </p>
                </section>
            </div>

            {{-- Casos por día --}}
            <section class="rounded-tarjeta border border-linea p-4">
                <h2 class="titulo-seccion mb-3">Casos por día del mes</h2>
                @php $maximo = max($c['por_dia'] ?: [1]); @endphp
                <div class="flex h-32 items-end gap-[3px] border-b border-linea">
                    @for ($dia = 1; $dia <= 31; $dia++)
                        @php $valor = $c['por_dia'][$dia] ?? 0; @endphp
                        <div class="flex h-full flex-1 flex-col items-center justify-end" title="Día {{ $dia }}: {{ $valor }}">
                            @if ($valor)
                                <span class="hidden font-mono text-[9px] text-tinta-3 sm:block">{{ $valor }}</span>
                            @endif
                            <div class="w-full rounded-t-[2px] bg-acento"
                                style="height: {{ $maximo > 0 ? round($valor / $maximo * 100) : 0 }}%"></div>
                        </div>
                    @endfor
                </div>
                <div class="mt-1 flex justify-between font-mono text-[10px] text-tinta-3">
                    <span>1</span><span>10</span><span>20</span><span>31</span>
                </div>
            </section>

            <div class="grid gap-4 md:grid-cols-2 print:grid-cols-2">
                {{-- Por falla --}}
                <section class="rounded-tarjeta border border-linea p-4">
                    <h2 class="titulo-seccion mb-3">Fallas reportadas</h2>
                    @forelse ($c['por_falla'] as $nombre => $valor)
                        <div class="mb-2.5">
                            <div class="flex justify-between text-sm">
                                <span class="truncate pr-2 text-tinta-2">{{ $nombre }}</span>
                                <span class="font-mono font-medium">{{ $valor }}</span>
                            </div>
                            <div class="mt-1 h-1.5 rounded-full bg-elevada">
                                <div class="h-1.5 rounded-full bg-acento"
                                    style="width: {{ round($valor / max($c['por_falla']) * 100) }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-tinta-3">Sin fallas clasificadas en el período.</p>
                    @endforelse
                </section>

                {{-- Por estado --}}
                <section class="rounded-tarjeta border border-linea p-4">
                    <h2 class="titulo-seccion mb-3">Estados finales</h2>
                    @foreach (\App\Models\Soporte::ESTADOS as $clave => $etiqueta)
                        @php $valor = $c['por_estado'][$clave] ?? 0; @endphp
                        @if ($valor)
                            <div class="mb-2.5">
                                <div class="flex justify-between text-sm">
                                    <span class="text-tinta-2">{{ $etiqueta }}</span><span class="font-mono font-medium">{{ $valor }}</span>
                                </div>
                                <div class="mt-1 h-1.5 rounded-full bg-elevada">
                                    <div class="h-1.5 rounded-full bg-tinta-3"
                                        style="width: {{ round($valor / $c['total'] * 100) }}%"></div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </section>
            </div>

            {{-- Diagnósticos --}}
            @if ($c['por_diagnostico'])
                <section class="rounded-tarjeta border border-linea p-4">
                    <h2 class="titulo-seccion mb-2">Diagnósticos aplicados</h2>
                    <div class="grid gap-x-6 sm:grid-cols-2 print:grid-cols-2">
                        @foreach ($c['por_diagnostico'] as $nombre => $valor)
                            <div class="flex justify-between gap-3 border-b border-linea-suave py-1.5 text-sm">
                                <span class="text-tinta-2">{{ $nombre }}</span><span class="font-mono font-medium">{{ $valor }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        @endif

        {{-- Observaciones --}}
        <section class="rounded-tarjeta border border-linea p-4 print:break-inside-avoid">
            <h2 class="titulo-seccion mb-3">Observaciones y oportunidades de mejora</h2>

            @if ($this->puedeEditar)
                <label for="observaciones" class="sr-only">Observaciones</label>
                <textarea id="observaciones" wire:model="observaciones" rows="6" class="control no-imprimir"
                    placeholder="Qué se hizo bien, qué conviene ajustar, en qué enfocarse el mes entrante..."></textarea>
                @error('observaciones') <p class="error-campo">{{ $message }}</p> @enderror
                {{-- Al imprimir un borrador sale el texto, no la caja de edición. --}}
                <p class="hidden whitespace-pre-line text-sm print:block">{{ $observaciones ?: 'Sin observaciones registradas.' }}</p>

                <label for="compromisos" class="etiqueta mt-4 {{ $compromisos ? '' : 'print:hidden' }}">Compromisos para el próximo mes</label>
                <textarea id="compromisos" wire:model="compromisos" rows="3" class="control no-imprimir"
                    placeholder="Acuerdos concretos y medibles..."></textarea>
                @error('compromisos') <p class="error-campo">{{ $message }}</p> @enderror
                <p class="hidden whitespace-pre-line text-sm print:block">{{ $compromisos }}</p>
            @else
                <p class="whitespace-pre-line text-sm">
                    {{ $informe->observaciones ?: 'Sin observaciones registradas.' }}
                </p>

                @if ($informe->compromisos)
                    <h3 class="mb-1 mt-4 text-sm font-semibold">Compromisos para el próximo mes</h3>
                    <p class="whitespace-pre-line text-sm">{{ $informe->compromisos }}</p>
                @endif
            @endif
        </section>

        {{-- Firmas --}}
        <div class="grid gap-8 pt-10 sm:grid-cols-2 print:grid-cols-2 print:break-inside-avoid">
            <div class="border-t border-tinta-3 pt-1.5 text-xs text-tinta-2">
                {{ $informe->tecnico->name }} · Técnico de soporte
            </div>
            <div class="border-t border-tinta-3 pt-1.5 text-xs text-tinta-2">
                {{ $informe->generadoPor->name ?? 'Coordinación técnica' }}
            </div>
        </div>

        @if ($informe->visto_at)
            <p class="text-xs text-tinta-3">
                Recibido por el técnico el <span class="font-mono">{{ $informe->visto_at->format('d/m/Y H:i') }}</span>.
            </p>
        @endif
    </article>
</div>
