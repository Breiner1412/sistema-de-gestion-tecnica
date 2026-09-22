<?php

use App\Models\InformeMensual;
use App\Models\Soporte;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    #[Url(except: '')]
    public string $anio = '';

    #[Url(except: '')]
    public string $mes = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->tieneRol(User::ROL_ADMIN, User::ROL_GERENTE), 403);

        $anterior = now()->subMonth();
        $this->anio = $this->anio ?: (string) $anterior->year;
        $this->mes = $this->mes ?: (string) $anterior->month;
    }

    /** Años en los que hay casos, para no ofrecer periodos vacíos. */
    #[Computed]
    public function anios(): array
    {
        return Soporte::selectRaw('distinct year(created_at) as anio')
            ->orderByDesc('anio')
            ->pluck('anio')
            ->all();
    }

    /** Carga del mes por técnico, con el informe asociado si ya existe. */
    #[Computed]
    public function filas()
    {
        $inicio = now()->setDate((int) $this->anio, (int) $this->mes, 1)->startOfMonth();
        $fin = (clone $inicio)->endOfMonth();

        $carga = Soporte::whereNotNull('tecnico_soporte_id')
            ->whereBetween('created_at', [$inicio, $fin])
            ->select('tecnico_soporte_id', DB::raw('count(*) as total'),
                DB::raw("sum(case when estado = 'solucionado' then 1 else 0 end) as resueltos"))
            ->groupBy('tecnico_soporte_id')
            ->get()
            ->keyBy('tecnico_soporte_id');

        $informes = InformeMensual::delPeriodo((int) $this->anio, (int) $this->mes)
            ->get()
            ->keyBy('tecnico_id');

        return User::tecnicosSoporte()->get(['id', 'name'])->map(function ($tecnico) use ($carga, $informes) {
            $fila = $carga->get($tecnico->id);

            return (object) [
                'tecnico' => $tecnico,
                'total' => $fila->total ?? 0,
                'resueltos' => $fila->resueltos ?? 0,
                'informe' => $informes->get($tecnico->id),
            ];
        })->sortByDesc('total')->values();
    }

    public function abrir(int $tecnicoId)
    {
        $informe = InformeMensual::firstOrCreate(
            ['tecnico_id' => $tecnicoId, 'anio' => (int) $this->anio, 'mes' => (int) $this->mes],
            ['generado_por_id' => auth()->id()],
        );

        return $this->redirect(route('informes.show', $informe), navigate: true);
    }
}; ?>

<div class="p-6">
    <div class="flex flex-wrap justify-between items-start gap-3 mb-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Informes de rendimiento</h1>
            <p class="text-sm text-slate-500 mt-1">
                Un informe por técnico y por mes, con las cifras del período y un espacio de
                observaciones para la retroalimentación.
            </p>
        </div>

        <div class="flex gap-2">
            <select wire:model.live="mes" class="border rounded p-2 text-sm">
                @foreach (\App\Models\InformeMensual::MESES as $numero => $nombre)
                    <option value="{{ $numero }}">{{ $nombre }}</option>
                @endforeach
            </select>
            <select wire:model.live="anio" class="border rounded p-2 text-sm">
                @foreach ($this->anios as $a)
                    <option value="{{ $a }}">{{ $a }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="overflow-x-auto bg-white border rounded shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-700 text-white">
                <tr>
                    <th class="p-2 text-left font-semibold">Técnico</th>
                    <th class="p-2 text-right font-semibold">Casos del mes</th>
                    <th class="p-2 text-right font-semibold">Resueltos</th>
                    <th class="p-2 text-left font-semibold">Informe</th>
                    <th class="p-2 text-right font-semibold"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse ($this->filas as $fila)
                    <tr class="hover:bg-slate-50">
                        <td class="p-2">{{ $fila->tecnico->name }}</td>
                        <td class="p-2 text-right font-medium">{{ $fila->total }}</td>
                        <td class="p-2 text-right text-slate-600">{{ $fila->resueltos }}</td>
                        <td class="p-2">
                            @if ($fila->informe?->estaPublicado())
                                <span class="px-2 py-1 rounded bg-green-200 text-green-900 text-xs">
                                    Publicado {{ $fila->informe->publicado_at->format('d/m/Y') }}
                                </span>
                            @elseif ($fila->informe)
                                <span class="px-2 py-1 rounded bg-yellow-200 text-yellow-900 text-xs">Borrador</span>
                            @else
                                <span class="text-slate-400 text-xs">Sin generar</span>
                            @endif
                        </td>
                        <td class="p-2 text-right">
                            <button wire:click="abrir({{ $fila->tecnico->id }})" class="text-blue-600 text-xs">
                                {{ $fila->informe ? 'Abrir' : 'Generar' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-6 text-center text-slate-500">
                            No hay técnicos de soporte registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
