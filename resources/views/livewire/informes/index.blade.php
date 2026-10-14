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
        // La gestión ve la carga del equipo; el técnico de soporte, sus informes.
        if (! $this->esGestion) {
            $this->authorize('verLosPropios', InformeMensual::class);

            return;
        }

        $this->normalizarPeriodo();
    }

    #[Computed]
    public function esGestion(): bool
    {
        return auth()->user()->can('viewAny', InformeMensual::class);
    }

    /** Los informes del técnico que ya se publicaron: un borrador todavía no es suyo. */
    #[Computed]
    public function misInformes()
    {
        return InformeMensual::where('tecnico_id', auth()->id())
            ->whereNotNull('publicado_at')
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->get();
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

<div class="mx-auto flex max-w-[1100px] flex-col gap-4 px-4 py-5 sm:px-6 lg:py-6">
  @if (! $this->esGestion)
    <x-encabezado titulo="Mis informes"
        subtitulo="Los informes de rendimiento que la coordinación ya te entregó, del más reciente al más antiguo." />

    @if ($this->misInformes->isEmpty())
        <p class="tarjeta p-6 text-center text-sm text-tinta-3">
            Todavía no tienes informes publicados. Aparecen aquí cuando la coordinación publica el de un mes.
        </p>
    @else
        <ul class="tarjeta divide-y divide-linea-suave">
            @foreach ($this->misInformes as $informe)
                <li wire:key="informe-{{ $informe->id }}">
                    <a href="{{ route('informes.show', $informe) }}" wire:navigate
                       class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-tinta no-underline transition-colors hover:bg-elevada">
                        <span class="font-medium">{{ $informe->periodo() }}</span>
                        <span class="flex items-center gap-3 text-xs text-tinta-3">
                            <span>Publicado <span class="font-mono">{{ $informe->publicado_at->format('d/m/Y') }}</span></span>
                            @if ($informe->visto_at)
                                <span class="inline-flex items-center gap-1 text-sla-ok">
                                    <svg width="10" height="10" viewBox="0 0 10 10" aria-hidden="true"><path d="M1.5 5.2 4 7.6 8.6 2.6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                    Leído
                                </span>
                            @else
                                <span class="rounded-[3px] bg-acento-tinte px-1.5 font-semibold text-acento-tinte-texto">Nuevo</span>
                            @endif
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
  @else
    <x-encabezado titulo="Informes de rendimiento"
        subtitulo="Un informe por técnico y por mes, con las cifras del período y un espacio de observaciones para la retroalimentación.">
        <x-slot:acciones>
            <label for="mes" class="sr-only">Mes</label>
            <select id="mes" wire:model.live="mes" class="control h-9 w-auto py-0 pr-9">
                @foreach (\App\Models\InformeMensual::MESES as $numero => $nombre)
                    <option value="{{ $numero }}">{{ $nombre }}</option>
                @endforeach
            </select>
            <label for="anio" class="sr-only">Año</label>
            <select id="anio" wire:model.live="anio" class="control h-9 w-auto py-0 pr-9 font-mono">
                @foreach ($this->anios as $a)
                    <option value="{{ $a }}">{{ $a }}</option>
                @endforeach
            </select>
        </x-slot:acciones>
    </x-encabezado>

    <x-tabla minimo="620px">
        <thead>
            <tr>
                <th>Técnico</th>
                <th class="text-right">Casos del mes</th>
                <th class="text-right">Resueltos</th>
                <th>Informe</th>
                <th class="text-right"><span class="sr-only">Acción</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($this->filas as $fila)
                <tr wire:key="tecnico-{{ $fila->tecnico->id }}">
                    <td class="font-medium">{{ $fila->tecnico->name }}</td>
                    <td class="text-right font-mono font-medium">{{ $fila->total }}</td>
                    <td class="text-right font-mono text-tinta-2">{{ $fila->resueltos }}</td>
                    <td>
                        @if ($fila->informe?->estaPublicado())
                            <span class="inline-flex h-[22px] items-center gap-1.5 rounded-full border border-linea bg-elevada px-2 text-xs font-medium text-sla-ok">
                                <svg width="10" height="10" viewBox="0 0 10 10" aria-hidden="true"><path d="M1.5 5.2 4 7.6 8.6 2.6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                Publicado <span class="font-mono">{{ $fila->informe->publicado_at->format('d/m/Y') }}</span>
                            </span>
                        @elseif ($fila->informe)
                            <span class="inline-flex h-[22px] items-center gap-1.5 rounded-full border border-dashed border-tinta-3 px-2 text-xs font-medium text-tinta-2">
                                <svg width="10" height="10" viewBox="0 0 16 16" aria-hidden="true"><path d="M10.5 2.5l3 3L6 13H3v-3l7.5-7.5Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"></path></svg>
                                Borrador
                            </span>
                        @else
                            <span class="text-xs text-tinta-3">Sin generar</span>
                        @endif
                    </td>
                    <td class="text-right">
                        <x-boton tamano="chico" :variante="$fila->informe ? 'secundario' : 'fantasma'" wire:click="abrir({{ $fila->tecnico->id }})">
                            {{ $fila->informe ? 'Abrir' : 'Generar' }}
                        </x-boton>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="!h-24 text-center text-tinta-3">No hay técnicos de soporte registrados.</td>
                </tr>
            @endforelse
        </tbody>
    </x-tabla>
  @endif
</div>
