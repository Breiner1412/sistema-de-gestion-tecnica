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
        $this->authorize('viewAny', InformeMensual::class);

        $this->normalizarPeriodo();
    }

    public function updated($propiedad): void
    {
        if (in_array($propiedad, ['anio', 'mes'], true)) {
            $this->normalizarPeriodo();
        }
    }

    /**
     * El año y el mes llegan por la URL: "mes=13" o "anio=abc" no pueden
     * llegar a la consulta. Lo que no es un periodo válido vuelve al mes pasado.
     */
    private function normalizarPeriodo(): void
    {
        $anterior = now()->subMonth();

        $anioValido = ctype_digit($this->anio) && in_array((int) $this->anio, $this->anios, true);
        $mesValido = ctype_digit($this->mes) && (int) $this->mes >= 1 && (int) $this->mes <= 12;

        $this->anio = $anioValido ? (string) (int) $this->anio : (string) $anterior->year;
        $this->mes = $mesValido ? (string) (int) $this->mes : (string) $anterior->month;
    }

    /**
     * Años en los que hay casos, para no ofrecer periodos vacíos. Con min y
     * max en vez de YEAR(), que no existe en SQLite: así lo cubren las pruebas.
     */
    #[Computed]
    public function anios(): array
    {
        $primero = Soporte::min('created_at');
        $ultimo = Soporte::max('created_at');
        $actual = now()->year;

        $desde = $primero ? min(\Carbon\Carbon::parse($primero)->year, $actual) : $actual;
        $hasta = $ultimo ? max(\Carbon\Carbon::parse($ultimo)->year, $actual) : $actual;

        return range($hasta, $desde);
    }

    /** Carga del mes por técnico, con el informe asociado si ya existe. */
    #[Computed]
    public function filas()
    {
        $inicio = now()->setDate((int) $this->anio, (int) $this->mes, 1)->startOfMonth();
        $fin = $inicio->copy()->endOfMonth();

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
        $this->authorize('create', InformeMensual::class);

        // Solo hay informe de quien atiende casos en el nivel 2 (aunque hoy
        // esté inactivo: el informe de un mes pasado sigue siendo suyo).
        abort_unless(
            User::whereKey($tecnicoId)->whereIn('rol', [User::ROL_TECNICO_SOPORTE, User::ROL_ADMIN])->exists(),
            404,
        );

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
