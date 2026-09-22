<?php

use App\Models\Cliente;
use App\Models\Diagnostico;
use App\Models\Soporte;
use App\Models\TipoFalla;
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

    public function with(): array
    {
        $consulta = Cliente::recurrentes((int) $this->meses, $this->minimo)
            ->orderByDesc('reportes')
            ->orderByDesc('ultimo_reporte');

        $pagina = $consulta->paginate(25);

        // El motivo se decide por fila con las cifras que ya vienen en la consulta.
        $pagina->getCollection()->transform(function ($cliente) {
            $cliente->motivo = $cliente->sin_contacto >= $cliente->reportes / 2
                ? Cliente::MOTIVO_SIN_CONTACTO
                : Cliente::MOTIVO_FALLA;

            return $cliente;
        });

        if ($this->motivo) {
            $pagina->setCollection($pagina->getCollection()->where('motivo', $this->motivo)->values());
        }

        return ['clientes' => $pagina];
    }
}; ?>

<div class="p-6">
    <div class="flex flex-wrap justify-between items-start gap-3 mb-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Abonados recurrentes</h1>
            <p class="text-sm text-slate-500 mt-1">
                Clientes con {{ $minimo }} o más reportes en la ventana. Son dos problemas distintos:
                una falla que no se resolvió de raíz, o un cliente al que nunca se logra contactar.
            </p>
        </div>

        <div class="flex gap-2">
            <select wire:model.live="meses" class="border rounded p-2 text-sm">
                <option value="2">Últimos 2 meses</option>
                <option value="6">Últimos 6 meses</option>
                <option value="12">Últimos 12 meses</option>
                <option value="0">Todo el histórico</option>
            </select>
            <select wire:model.live="motivo" class="border rounded p-2 text-sm">
                <option value="">Todos los motivos</option>
                <option value="falla_recurrente">Falla recurrente</option>
                <option value="sin_contacto">No se logra contactar</option>
            </select>
        </div>
    </div>

    {{-- Patrones --}}
    <div class="grid gap-4 md:grid-cols-2 mb-4">
        <div class="bg-white border rounded p-4">
            <h2 class="font-semibold text-slate-800 mb-2 text-sm">Qué reportan los recurrentes</h2>
            @forelse ($this->patrones['fallas'] as $nombre => $total)
                <div class="flex justify-between text-sm text-slate-700 py-1 border-b last:border-0">
                    <span>{{ $nombre }}</span><span class="font-medium">{{ $total }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-500">Sin datos en la ventana.</p>
            @endforelse
        </div>

        <div class="bg-white border rounded p-4">
            <h2 class="font-semibold text-slate-800 mb-2 text-sm">Qué se les diagnosticó</h2>
            @forelse ($this->patrones['diagnosticos'] as $nombre => $total)
                <div class="flex justify-between text-sm text-slate-700 py-1 border-b last:border-0">
                    <span>{{ $nombre }}</span><span class="font-medium">{{ $total }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-500">Todavía no hay diagnósticos registrados.</p>
            @endforelse
        </div>
    </div>

    <div class="overflow-x-auto bg-white border rounded shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-700 text-white">
                <tr>
                    <th class="p-2 text-left font-semibold">Abonado</th>
                    <th class="p-2 text-left font-semibold">Cliente</th>
                    <th class="p-2 text-right font-semibold">Reportes</th>
                    <th class="p-2 text-right font-semibold">Sin contacto</th>
                    <th class="p-2 text-right font-semibold">Abiertos</th>
                    <th class="p-2 text-left font-semibold">Motivo probable</th>
                    <th class="p-2 text-left font-semibold">Último</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse ($clientes as $cliente)
                    <tr class="hover:bg-slate-50 cursor-pointer"
                        onclick="window.location='{{ route('clientes.show', $cliente) }}'">
                        <td class="p-2 font-mono text-xs text-slate-600">{{ $cliente->codigo_abonado ?? '—' }}</td>
                        <td class="p-2">{{ $cliente->nombre }}</td>
                        <td class="p-2 text-right font-semibold">{{ $cliente->reportes }}</td>
                        <td class="p-2 text-right text-slate-600">{{ $cliente->sin_contacto }}</td>
                        <td class="p-2 text-right">
                            @if ($cliente->abiertos > 0)
                                <span class="text-orange-700 font-medium">{{ $cliente->abiertos }}</span>
                            @else
                                <span class="text-slate-400">0</span>
                            @endif
                        </td>
                        <td class="p-2">
                            <span @class([
                                'px-2 py-1 rounded text-xs whitespace-nowrap',
                                'bg-amber-200 text-amber-900' => $cliente->motivo === 'falla_recurrente',
                                'bg-orange-200 text-orange-900' => $cliente->motivo === 'sin_contacto',
                            ])>
                                {{ $cliente->motivo === 'sin_contacto' ? 'No se logra contactar' : 'Falla recurrente' }}
                            </span>
                        </td>
                        <td class="p-2 text-slate-500 text-xs whitespace-nowrap">
                            {{ $cliente->ultimo_reporte ? \Carbon\Carbon::parse($cliente->ultimo_reporte)->format('d/m/Y') : '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-6 text-center text-slate-500">
                            Ningún abonado alcanza {{ $minimo }} reportes en esta ventana.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $clientes->links() }}</div>

    <p class="mt-3 text-xs text-slate-500">
        Un abonado con falla recurrente necesita revisión de instalación, no otro soporte remoto.
        Uno que nunca contesta necesita otro canal o una visita agendada, no otra llamada.
    </p>
</div>
