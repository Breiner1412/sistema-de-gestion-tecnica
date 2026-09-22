<?php

use App\Models\InformeMensual;
use App\Models\User;
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
        abort_unless(
            $usuario->tieneRol(User::ROL_ADMIN, User::ROL_GERENTE) || $usuario->id === $informe->tecnico_id,
            403
        );

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
        return auth()->user()->tieneRol(User::ROL_ADMIN, User::ROL_GERENTE)
            && !$this->informe->estaPublicado();
    }

    #[Computed]
    public function cifras(): array
    {
        return $this->informe->cifras();
    }

    public function guardar(): void
    {
        abort_unless($this->puedeEditar, 403);

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
        abort_unless($this->puedeEditar, 403);

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

<div class="p-6 max-w-5xl">
    <style>
        @media print {
            nav, .no-imprimir { display: none !important; }
            body { background: #fff; }
            .pagina { box-shadow: none !important; border: 0 !important; }
        }
    </style>

    @if (session('mensaje'))
        <div class="bg-green-100 text-green-800 p-3 rounded mb-4 no-imprimir">{{ session('mensaje') }}</div>
    @endif

    <div class="flex flex-wrap justify-between items-center gap-3 mb-4 no-imprimir">
        <a href="{{ route('informes.index') }}" wire:navigate class="text-blue-600 text-sm">&larr; Volver a informes</a>

        <div class="flex gap-2">
            <button onclick="window.print()" class="px-4 py-2 rounded border text-sm">Imprimir</button>

            @if ($this->puedeEditar)
                <button wire:click="guardar" class="px-4 py-2 rounded border text-sm">Guardar borrador</button>
                <button wire:click="publicar" wire:confirm="Al publicar, las cifras quedan congeladas y el informe ya no se puede editar. ¿Continuar?"
                    class="bg-blue-600 text-white px-4 py-2 rounded text-sm">Publicar</button>
            @endif
        </div>
    </div>

    @php $c = $this->cifras; @endphp

    <div class="pagina bg-white border rounded shadow-sm p-6 space-y-6">

        {{-- Encabezado --}}
        <div class="flex flex-wrap justify-between items-start gap-4 border-b pb-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Informe de rendimiento</h1>
                <p class="text-slate-600">{{ $informe->tecnico->name }} · {{ $informe->periodo() }}</p>
            </div>
            <div class="text-right">
                <p class="text-xs uppercase tracking-wide text-slate-500">Casos atendidos</p>
                <p class="text-4xl font-bold text-slate-800">{{ $c['total'] }}</p>
                @if ($informe->estaPublicado())
                    <p class="text-xs text-green-700 mt-1">Publicado {{ $informe->publicado_at->format('d/m/Y') }}</p>
                @else
                    <p class="text-xs text-yellow-700 mt-1">Borrador</p>
                @endif
            </div>
        </div>

        @if ($c['total'] === 0)
            <p class="text-slate-500">Este técnico no tiene casos registrados en el período.</p>
        @else
            {{-- Indicadores --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @php
                    $tarjetas = [
                        ['Resueltos', $c['resueltos'], $c['tasa_resolucion'] !== null ? $c['tasa_resolucion'].'% del total' : ''],
                        ['Sin contacto', $c['sin_contacto'], $c['tasa_sin_contacto'] !== null ? $c['tasa_sin_contacto'].'% del total' : ''],
                        ['Escalados a redes', $c['escalados'], 'enviados a nivel 3'],
                        ['Enviados a terreno', $c['enviados_campo'], 'requirieron visita'],
                    ];
                @endphp

                @foreach ($tarjetas as [$titulo, $valor, $pie])
                    <div class="border rounded p-3">
                        <p class="text-xs uppercase tracking-wide text-slate-500">{{ $titulo }}</p>
                        <p class="text-2xl font-bold text-slate-800 mt-1">{{ $valor }}</p>
                        <p class="text-xs text-slate-500">{{ $pie }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Ritmo y comparación --}}
            <div class="grid gap-4 md:grid-cols-2">
                <div class="border rounded p-4">
                    <h2 class="font-semibold text-slate-800 mb-2">Ritmo de trabajo</h2>
                    <dl class="text-sm space-y-1 text-slate-700">
                        <div class="flex justify-between"><dt>Días con actividad</dt><dd class="font-medium">{{ $c['dias_trabajados'] }}</dd></div>
                        <div class="flex justify-between"><dt>Promedio por día activo</dt><dd class="font-medium">{{ $c['promedio_diario'] ?? '—' }}</dd></div>
                        <div class="flex justify-between"><dt>Día más cargado</dt><dd class="font-medium">{{ $c['mejor_dia'] ? 'día '.$c['mejor_dia'] : '—' }}</dd></div>
                        <div class="flex justify-between">
                            <dt>Tiempo medio de resolución</dt>
                            <dd class="font-medium">{{ $c['promedio_resolucion'] !== null ? $c['promedio_resolucion'].' min hábiles' : 'sin datos' }}</dd>
                        </div>
                        <div class="flex justify-between"><dt>Casos inmediatos atendidos</dt><dd class="font-medium">{{ $c['inmediatos'] }}</dd></div>
                    </dl>
                </div>

                <div class="border rounded p-4">
                    <h2 class="font-semibold text-slate-800 mb-2">Frente al equipo</h2>
                    <dl class="text-sm space-y-1 text-slate-700">
                        <div class="flex justify-between"><dt>Técnicos activos en el mes</dt><dd class="font-medium">{{ $c['equipo']['tecnicos_activos'] }}</dd></div>
                        <div class="flex justify-between"><dt>Promedio de casos del equipo</dt><dd class="font-medium">{{ $c['equipo']['promedio_casos'] ?? '—' }}</dd></div>
                        <div class="flex justify-between"><dt>Participación en la carga</dt><dd class="font-medium">{{ $c['equipo']['participacion'] !== null ? $c['equipo']['participacion'].'%' : '—' }}</dd></div>
                        <div class="flex justify-between">
                            <dt>Posición por volumen</dt>
                            <dd class="font-medium">{{ $c['equipo']['posicion'] ? $c['equipo']['posicion'].' de '.$c['equipo']['tecnicos_activos'] : '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>Resolución media del equipo</dt>
                            <dd class="font-medium">{{ $c['equipo']['promedio_resolucion'] !== null ? $c['equipo']['promedio_resolucion'].' min' : 'sin datos' }}</dd>
                        </div>
                    </dl>
                    <p class="text-xs text-slate-500 mt-2">
                        El volumen no es calidad: sirve para leer la carga, no para rankear personas.
                    </p>
                </div>
            </div>

            {{-- Casos por día --}}
            <div class="border rounded p-4">
                <h2 class="font-semibold text-slate-800 mb-3">Casos por día del mes</h2>
                @php $maximo = max($c['por_dia'] ?: [1]); @endphp
                <div class="flex items-end gap-1 h-32">
                    @for ($dia = 1; $dia <= 31; $dia++)
                        @php $valor = $c['por_dia'][$dia] ?? 0; @endphp
                        <div class="flex-1 flex flex-col items-center justify-end h-full" title="Día {{ $dia }}: {{ $valor }}">
                            @if ($valor)
                                <span class="text-[9px] text-slate-500">{{ $valor }}</span>
                            @endif
                            <div class="w-full bg-amber-500 rounded-t"
                                style="height: {{ $maximo > 0 ? round($valor / $maximo * 100) : 0 }}%"></div>
                        </div>
                    @endfor
                </div>
                <div class="flex justify-between text-[10px] text-slate-400 mt-1">
                    <span>1</span><span>10</span><span>20</span><span>31</span>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                {{-- Por falla --}}
                <div class="border rounded p-4">
                    <h2 class="font-semibold text-slate-800 mb-3">Fallas reportadas</h2>
                    @forelse ($c['por_falla'] as $nombre => $valor)
                        <div class="mb-2">
                            <div class="flex justify-between text-sm text-slate-700">
                                <span class="truncate pr-2">{{ $nombre }}</span>
                                <span class="font-medium">{{ $valor }}</span>
                            </div>
                            <div class="h-1.5 bg-slate-100 rounded mt-1">
                                <div class="h-1.5 bg-amber-500 rounded"
                                    style="width: {{ round($valor / max($c['por_falla']) * 100) }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Sin fallas clasificadas en el período.</p>
                    @endforelse
                </div>

                {{-- Por estado --}}
                <div class="border rounded p-4">
                    <h2 class="font-semibold text-slate-800 mb-3">Estados finales</h2>
                    @foreach (\App\Models\Soporte::ESTADOS as $clave => $etiqueta)
                        @php $valor = $c['por_estado'][$clave] ?? 0; @endphp
                        @if ($valor)
                            <div class="mb-2">
                                <div class="flex justify-between text-sm text-slate-700">
                                    <span>{{ $etiqueta }}</span><span class="font-medium">{{ $valor }}</span>
                                </div>
                                <div class="h-1.5 bg-slate-100 rounded mt-1">
                                    <div class="h-1.5 bg-slate-600 rounded"
                                        style="width: {{ round($valor / $c['total'] * 100) }}%"></div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- Diagnósticos --}}
            @if ($c['por_diagnostico'])
                <div class="border rounded p-4">
                    <h2 class="font-semibold text-slate-800 mb-3">Diagnósticos aplicados</h2>
                    <div class="grid gap-x-6 sm:grid-cols-2">
                        @foreach ($c['por_diagnostico'] as $nombre => $valor)
                            <div class="flex justify-between text-sm text-slate-700 py-1 border-b">
                                <span>{{ $nombre }}</span><span class="font-medium">{{ $valor }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif

        {{-- Observaciones --}}
        <div class="border rounded p-4">
            <h2 class="font-semibold text-slate-800 mb-3">Observaciones y oportunidades de mejora</h2>

            @if ($this->puedeEditar)
                <textarea wire:model="observaciones" rows="6" class="w-full border rounded p-2 text-sm no-imprimir"
                    placeholder="Qué se hizo bien, qué conviene ajustar, en qué enfocarse el mes entrante..."></textarea>
                @error('observaciones') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror

                <label class="block font-medium mt-4 mb-1 text-slate-800">Compromisos para el próximo mes</label>
                <textarea wire:model="compromisos" rows="3" class="w-full border rounded p-2 text-sm no-imprimir"
                    placeholder="Acuerdos concretos y medibles..."></textarea>
                @error('compromisos') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            @else
                <p class="text-sm text-slate-700 whitespace-pre-line">
                    {{ $informe->observaciones ?: 'Sin observaciones registradas.' }}
                </p>

                @if ($informe->compromisos)
                    <h3 class="font-medium text-slate-800 mt-4 mb-1">Compromisos para el próximo mes</h3>
                    <p class="text-sm text-slate-700 whitespace-pre-line">{{ $informe->compromisos }}</p>
                @endif
            @endif
        </div>

        {{-- Firmas --}}
        <div class="grid gap-8 sm:grid-cols-2 pt-8">
            <div>
                <div class="border-t border-slate-400 pt-1 text-xs text-slate-600">
                    {{ $informe->tecnico->name }} · Técnico de soporte
                </div>
            </div>
            <div>
                <div class="border-t border-slate-400 pt-1 text-xs text-slate-600">
                    {{ $informe->generadoPor->name ?? 'Coordinación técnica' }}
                </div>
            </div>
        </div>

        @if ($informe->visto_at)
            <p class="text-xs text-slate-500">
                Recibido por el técnico el {{ $informe->visto_at->format('d/m/Y H:i') }}.
            </p>
        @endif
    </div>
</div>
