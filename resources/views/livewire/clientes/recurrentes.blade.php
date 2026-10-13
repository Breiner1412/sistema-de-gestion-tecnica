<?php

use App\Models\Cliente;
use App\Models\Diagnostico;
use App\Models\Soporte;
use App\Models\TipoFalla;
use App\Support\ExportadorCsv;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Url(except: '12')]
    public string $meses = '12';

    #[Url(except: '')]
    public string $motivo = ''; // '', 'falla_recurrente', 'sin_contacto'

    public int $minimo = 3;

    public function mount(): void
    {
        $this->authorize('viewAny', Cliente::class);

        $this->minimo = (int) config('sla.recurrencia.minimo_en_ventana_larga', 3);
    }

    public function updated($propiedad): void
    {
        if ($propiedad !== 'page') {
            $this->resetPage();
        }
    }

    /** Falla y diagnóstico que más se repiten entre los abonados listados. */
    #[Computed]
    public function patrones(): array
    {
        $desde = (int) $this->meses > 0 ? now()->subMonths((int) $this->meses) : null;

        $base = fn($q) => $desde ? $q->where('soportes.created_at', '>=', $desde) : $q;

        $reincidentes = DB::table('soportes')
            ->select('cliente_id')
            ->when($desde, fn($q) => $q->where('created_at', '>=', $desde))
            ->groupBy('cliente_id')
            ->havingRaw('count(*) >= ?', [$this->minimo]);

        return [
            'fallas' => $base(
                Soporte::query()
                    ->joinSub($reincidentes, 'r', 'r.cliente_id', '=', 'soportes.cliente_id')
                    ->join('tipos_falla', 'tipos_falla.id', '=', 'soportes.tipo_falla_id')
            )
                ->select('tipos_falla.nombre', DB::raw('count(*) as total'))
                ->groupBy('tipos_falla.nombre')
                ->orderByDesc('total')
                ->limit(5)
                ->pluck('total', 'tipos_falla.nombre'),

            'diagnosticos' => $base(
                Soporte::query()
                    ->joinSub($reincidentes, 'r2', 'r2.cliente_id', '=', 'soportes.cliente_id')
                    ->join('diagnosticos', 'diagnosticos.id', '=', 'soportes.diagnostico_id')
            )
                ->select('diagnosticos.nombre', DB::raw('count(*) as total'))
                ->groupBy('diagnosticos.nombre')
                ->orderByDesc('total')
                ->limit(5)
                ->pluck('total', 'diagnosticos.nombre'),
        ];
    }

    /** Mismo criterio que la tabla, sin paginar. El motivo se filtra en SQL. */
    private function consulta()
    {
        $motivo = in_array($this->motivo, [Cliente::MOTIVO_FALLA, Cliente::MOTIVO_SIN_CONTACTO], true)
            ? $this->motivo
            : null;

        return Cliente::recurrentes((int) $this->meses, $this->minimo, $motivo)
            ->orderByDesc('reportes')
            ->orderByDesc('ultimo_reporte');
    }

    /** El motivo no vive en la base: se decide con las cifras de cada fila. */
    private function motivoDe($cliente): string
    {
        return $cliente->sin_contacto >= $cliente->reportes / 2
            ? Cliente::MOTIVO_SIN_CONTACTO
            : Cliente::MOTIVO_FALLA;
    }

    public function exportar()
    {
        $this->authorize('viewAny', Cliente::class);

        $filas = $this->consulta()->get()
            ->map(fn($c) => [
                $c->codigo_abonado,
                $c->nombre,
                $c->cedula,
                $c->telefono,
                $c->reportes,
                $c->sin_contacto,
                $c->abiertos,
                $this->motivoDe($c) === Cliente::MOTIVO_SIN_CONTACTO ? 'No se logra contactar' : 'Falla recurrente',
                $c->primer_reporte,
                $c->ultimo_reporte,
            ]);

        return ExportadorCsv::descargar(
            ExportadorCsv::nombre('abonados-recurrentes'),
            ['Abonado', 'Cliente', 'Cédula', 'Teléfono', 'Reportes', 'Sin contacto',
             'Abiertos', 'Motivo probable', 'Primer reporte', 'Último reporte'],
            $filas,
        );
    }

    public function with(): array
    {
        $consulta = $this->consulta();

        $pagina = $consulta->paginate(25);

        // El motivo se decide por fila con las cifras que ya vienen en la consulta.
        $pagina->getCollection()->transform(function ($cliente) {
            $cliente->motivo = $this->motivoDe($cliente);

            return $cliente;
        });

        return ['clientes' => $pagina];
    }
}; ?>

<div class="mx-auto flex max-w-[1400px] flex-col gap-4 px-4 py-5 sm:px-6 lg:py-6">
    <x-encabezado titulo="Abonados recurrentes"
        subtitulo="Clientes con {{ $minimo }} o más reportes en la ventana. Son dos problemas distintos: una falla que no se resolvió de raíz, o un cliente al que nunca se logra contactar.">
        <x-slot:antes>
            <a href="{{ route('clientes.index') }}" wire:navigate class="inline-flex items-center gap-1 text-tinta-3 no-underline hover:text-acento-texto">
                <svg width="13" height="13" viewBox="0 0 16 16" aria-hidden="true"><path d="M10 3 5 8l5 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                Clientes
            </a>
        </x-slot:antes>
    </x-encabezado>

    <div class="flex flex-wrap items-center gap-2">
        <label for="meses" class="sr-only">Ventana</label>
        <select id="meses" wire:model.live="meses" class="control h-9 w-auto py-0 pr-9">
            <option value="2">Últimos 2 meses</option>
            <option value="6">Últimos 6 meses</option>
            <option value="12">Últimos 12 meses</option>
            <option value="0">Todo el histórico</option>
        </select>
        <label for="motivo" class="sr-only">Motivo</label>
        <select id="motivo" wire:model.live="motivo" class="control h-9 w-auto py-0 pr-9">
            <option value="">Todos los motivos</option>
            <option value="falla_recurrente">Falla recurrente</option>
            <option value="sin_contacto">No se logra contactar</option>
        </select>
        <x-boton wire:click="exportar" wire:loading.attr="disabled" class="sm:ml-auto">
            <svg width="15" height="15" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 2v8m0 0 3-3m-3 3L5 7M2.5 11v2.5h11V11" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
            <span wire:loading.remove wire:target="exportar">Exportar CSV</span>
            <span wire:loading wire:target="exportar">Generando…</span>
        </x-boton>
    </div>

    {{-- Patrones --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <section class="tarjeta p-4">
            <h2 class="titulo-seccion mb-2">Qué reportan los recurrentes</h2>
            @forelse ($this->patrones['fallas'] as $nombre => $total)
                <div class="flex justify-between gap-3 border-b border-linea-suave py-1.5 text-sm last:border-0">
                    <span class="text-tinta-2">{{ $nombre }}</span><span class="font-mono font-medium">{{ $total }}</span>
                </div>
            @empty
                <p class="text-sm text-tinta-3">Sin datos en la ventana.</p>
            @endforelse
        </section>

        <section class="tarjeta p-4">
            <h2 class="titulo-seccion mb-2">Qué se les diagnosticó</h2>
            @forelse ($this->patrones['diagnosticos'] as $nombre => $total)
                <div class="flex justify-between gap-3 border-b border-linea-suave py-1.5 text-sm last:border-0">
                    <span class="text-tinta-2">{{ $nombre }}</span><span class="font-mono font-medium">{{ $total }}</span>
                </div>
            @empty
                <p class="text-sm text-tinta-3">Todavía no hay diagnósticos registrados.</p>
            @endforelse
        </section>
    </div>

    <x-tabla minimo="820px">
        <thead>
            <tr>
                <th>Abonado</th>
                <th>Cliente</th>
                <th class="text-right">Reportes</th>
                <th class="text-right">Sin contacto</th>
                <th class="text-right">Abiertos</th>
                <th>Motivo probable</th>
                <th class="text-right">Último</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($clientes as $cliente)
                <tr wire:key="recurrente-{{ $cliente->id }}" class="cursor-pointer"
                    onclick="window.location='{{ route('clientes.show', $cliente) }}'">
                    <td class="font-mono text-tinta-2">{{ $cliente->codigo_abonado ?? '—' }}</td>
                    <td>
                        <a href="{{ route('clientes.show', $cliente) }}" class="font-medium text-tinta no-underline hover:text-acento-texto hover:underline">{{ $cliente->nombre }}</a>
                    </td>
                    <td class="text-right">
                        <span class="rounded-[3px] bg-recurrente-fondo px-1.5 font-mono font-semibold text-recurrente">↻ {{ $cliente->reportes }}</span>
                    </td>
                    <td class="text-right font-mono text-tinta-2">{{ $cliente->sin_contacto }}</td>
                    <td class="text-right font-mono">
                        @if ($cliente->abiertos > 0)
                            <span class="font-semibold">{{ $cliente->abiertos }}</span>
                        @else
                            <span class="text-tinta-3">0</span>
                        @endif
                    </td>
                    <td>
                        <span class="inline-flex h-[22px] items-center gap-1.5 whitespace-nowrap rounded-full border border-linea px-2 text-xs font-medium">
                            @if ($cliente->motivo === 'sin_contacto')
                                <span class="size-[7px] rounded-full bg-estado-sincontacto" aria-hidden="true"></span>
                                No se logra contactar
                            @else
                                <span class="size-[7px] rounded-full bg-recurrente" aria-hidden="true"></span>
                                Falla recurrente
                            @endif
                        </span>
                    </td>
                    <td class="whitespace-nowrap text-right font-mono text-xs text-tinta-3">
                        {{ $cliente->ultimo_reporte ? \Carbon\Carbon::parse($cliente->ultimo_reporte)->format('d/m/Y') : '—' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="!h-24 text-center text-tinta-3">
                        Ningún abonado alcanza {{ $minimo }} reportes en esta ventana.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-tabla>

    <div>{{ $clientes->links() }}</div>

    <p class="text-xs text-tinta-3">
        Un abonado con falla recurrente necesita revisión de instalación, no otro soporte remoto.
        Uno que nunca contesta necesita otro canal o una visita agendada, no otra llamada.
    </p>
</div>
