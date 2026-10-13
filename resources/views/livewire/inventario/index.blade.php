<?php

use App\Models\Inventario;
use App\Models\MovimientoInventario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(except: false)]
    public bool $soloBajos = false;

    public string $panel = '';

    // Los ids los ponen editarMaterial() y moverStock(): el navegador no los
    // puede cambiar por los de otro material entre abrir y guardar el panel.
    // Material
    #[Locked]
    public ?int $material_id = null;
    public string $nombre = '';
    public string $tipo = 'cable';
    public string $cantidad_minima = '0';
    public string $precio_unitario = '';

    // Movimiento
    #[Locked]
    public ?int $movimiento_material_id = null;
    public string $movimiento_tipo = 'entrada';
    public string $movimiento_cantidad = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Inventario::class);
    }

    public function updated($propiedad): void
    {
        if ($propiedad !== 'page') {
            $this->resetPage();
        }
    }

    #[Computed]
    public function bajos(): int
    {
        return Inventario::whereColumn('cantidad_total', '<=', 'cantidad_minima')->count();
    }

    /* ---------------------------------------------------------------
     | Materiales
     * --------------------------------------------------------------- */

    public function nuevoMaterial(): void
    {
        $this->reset(['material_id', 'nombre', 'precio_unitario']);
        $this->tipo = 'cable';
        $this->cantidad_minima = '0';
        $this->panel = 'material';
    }

    public function editarMaterial(int $id): void
    {
        $material = Inventario::findOrFail($id);
        $this->authorize('update', $material);

        $this->material_id = $material->id;
        $this->nombre = $material->nombre;
        $this->tipo = $material->tipo ?? 'cable';
        $this->cantidad_minima = (string) $material->cantidad_minima;
        $this->precio_unitario = (string) $material->precio_unitario;
        $this->panel = 'material';
    }

    public function guardarMaterial(): void
    {
        $this->material_id
            ? $this->authorize('update', Inventario::findOrFail($this->material_id))
            : $this->authorize('create', Inventario::class);

        $datos = $this->validate([
            'nombre' => ['required', 'string', 'max:120', Rule::unique('inventario', 'nombre')->ignore($this->material_id)],
            'tipo' => ['required', 'string', 'max:40'],
            'cantidad_minima' => ['required', 'integer', 'min:0'],
            'precio_unitario' => ['nullable', 'numeric', 'min:0'],
        ], [], ['cantidad_minima' => 'stock mínimo', 'precio_unitario' => 'precio unitario']);

        $atributos = [
            'nombre' => $datos['nombre'],
            'tipo' => $datos['tipo'],
            'cantidad_minima' => $datos['cantidad_minima'],
            'precio_unitario' => $datos['precio_unitario'] !== '' ? $datos['precio_unitario'] : null,
        ];

        // El stock nunca se edita a mano: solo se mueve por entradas y ajustes,
        // para que el kardex siempre cuadre con la existencia.
        $this->material_id
            ? Inventario::whereKey($this->material_id)->update($atributos)
            : Inventario::create($atributos + ['cantidad_total' => 0]);

        $this->panel = '';
        session()->flash('mensaje', 'Material guardado.');
    }

    /* ---------------------------------------------------------------
     | Movimientos
     * --------------------------------------------------------------- */

    public function moverStock(int $id): void
    {
        $this->movimiento_material_id = Inventario::findOrFail($id)->id;
        $this->movimiento_tipo = 'entrada';
        $this->movimiento_cantidad = '';
        $this->panel = 'movimiento';
    }

    public function guardarMovimiento(): void
    {
        $this->validate([
            'movimiento_material_id' => ['required', 'exists:inventario,id'],
            'movimiento_tipo' => [Rule::in(['entrada', 'salida'])],
            'movimiento_cantidad' => ['required', 'integer', 'min:1'],
        ], [], ['movimiento_cantidad' => 'cantidad']);

        $material = Inventario::findOrFail($this->movimiento_material_id);
        $this->authorize('update', $material);
        $cantidad = (int) $this->movimiento_cantidad;

        $aplicado = DB::transaction(function () use ($material, $cantidad) {
            // La salida lleva la condición en el UPDATE, como el consumo de
            // las visitas: dos salidas simultáneas no dejan el stock negativo.
            $filas = $this->movimiento_tipo === 'entrada'
                ? Inventario::whereKey($material->id)->increment('cantidad_total', $cantidad)
                : Inventario::whereKey($material->id)
                    ->where('cantidad_total', '>=', $cantidad)
                    ->decrement('cantidad_total', $cantidad);

            if ($filas === 0) {
                return false;
            }

            MovimientoInventario::create([
                'material_id' => $material->id,
                'tipo' => $this->movimiento_tipo,
                'cantidad' => $cantidad,
            ]);

            return true;
        });

        if (! $aplicado) {
            $disponible = Inventario::whereKey($material->id)->value('cantidad_total');
            $this->addError('movimiento_cantidad', "Solo hay {$disponible} unidades disponibles.");

            return;
        }

        $this->panel = '';
        unset($this->bajos);

        session()->flash('mensaje', 'Movimiento registrado.');
    }

    public function with(): array
    {
        return [
            'materiales' => Inventario::query()
                ->when($this->buscar, fn($q) => $q->where('nombre', 'like', "%{$this->buscar}%"))
                ->when($this->soloBajos, fn($q) => $q->whereColumn('cantidad_total', '<=', 'cantidad_minima'))
                ->orderBy('nombre')
                ->paginate(25),
        ];
    }
}; ?>

<div class="mx-auto flex max-w-[1400px] flex-col gap-4 px-4 py-5 sm:px-6 lg:py-6">
    <x-mensajes />

    <x-encabezado titulo="Inventario" subtitulo="Material de bodega para las visitas. La existencia se mueve solo con entradas y salidas.">
        <x-slot:acciones>
            <x-boton variante="primario" wire:click="nuevoMaterial">
                <svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>
                Nuevo material
            </x-boton>
        </x-slot:acciones>
    </x-encabezado>

    @if ($this->bajos > 0)
        <div class="flex flex-wrap items-center justify-between gap-2 rounded-tarjeta border border-sla-atencion/30 bg-sla-atencion-fondo px-4 py-3 text-sm text-sla-atencion">
            <span class="flex items-center gap-2">
                <x-sla-glifo estado="atencion" :tamano="12" />
                <span><strong class="font-mono">{{ $this->bajos }}</strong>
                {{ $this->bajos === 1 ? 'material está' : 'materiales están' }} en o por debajo del stock mínimo.</span>
            </span>
            <button type="button" wire:click="$set('soloBajos', true)" class="font-semibold underline">Ver solo esos</button>
        </div>
    @endif

    <div class="tarjeta grid items-end gap-3 p-3 sm:grid-cols-[minmax(0,1fr)_auto]">
        <div>
            <label for="buscar" class="etiqueta text-[13px]">Buscar</label>
            <div class="relative">
                <svg class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-tinta-3" width="15" height="15" viewBox="0 0 16 16" aria-hidden="true"><circle cx="7" cy="7" r="4.5" fill="none" stroke="currentColor" stroke-width="1.5"></circle><path d="m10.5 10.5 3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path></svg>
                <input id="buscar" type="search" wire:model.live.debounce.400ms="buscar" class="control h-9 pl-8" placeholder="Nombre del material">
            </div>
        </div>
        <label class="flex h-9 items-center gap-2 text-sm">
            <input type="checkbox" wire:model.live="soloBajos" class="size-4 rounded border-linea bg-superficie text-acento focus:ring-acento-texto">
            Solo stock bajo
        </label>
    </div>

    {{-- Panel de material --}}
    @if ($panel === 'material')
        <section class="tarjeta space-y-3 border-acento/40 p-4">
            <h2 class="font-semibold">{{ $material_id ? 'Editar material' : 'Nuevo material' }}</h2>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div class="sm:col-span-2">
                    <label for="nombre" class="etiqueta">Nombre</label>
                    <input id="nombre" type="text" wire:model="nombre" class="control h-9">
                    @error('nombre') <p class="error-campo">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="tipo" class="etiqueta">Categoría</label>
                    <select id="tipo" wire:model="tipo" class="control h-9 py-0">
                        <option value="cable">Cable</option>
                        <option value="conector">Conector</option>
                        <option value="router">Router / ONU</option>
                        <option value="equipo">Equipo</option>
                        <option value="accesorio">Accesorio</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
                <div>
                    <label for="cantidad-minima" class="etiqueta">Stock mínimo</label>
                    <input id="cantidad-minima" type="number" min="0" wire:model="cantidad_minima" class="control h-9 font-mono">
                    @error('cantidad_minima') <p class="error-campo">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="precio" class="etiqueta">Precio unitario</label>
                    <input id="precio" type="number" step="1" wire:model="precio_unitario" class="control h-9 font-mono">
                    @error('precio_unitario') <p class="error-campo">{{ $message }}</p> @enderror
                </div>
            </div>

            <p class="text-xs text-tinta-3">
                La existencia no se edita aquí: entra y sale por movimientos, para que el kardex siempre cuadre.
            </p>

            <div class="flex gap-2">
                <x-boton variante="primario" wire:click="guardarMaterial">Guardar</x-boton>
                <x-boton variante="fantasma" wire:click="$set('panel', '')">Cancelar</x-boton>
            </div>
        </section>
    @endif

    {{-- Panel de movimiento --}}
    @if ($panel === 'movimiento')
        <section class="tarjeta space-y-3 border-acento/40 p-4">
            <h2 class="font-semibold">Registrar movimiento</h2>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] lg:max-w-2xl">
                <div>
                    <label for="movimiento-tipo" class="etiqueta">Tipo</label>
                    <select id="movimiento-tipo" wire:model="movimiento_tipo" class="control h-9 py-0">
                        <option value="entrada">Entrada (compra o devolución a bodega)</option>
                        <option value="salida">Salida (baja o pérdida)</option>
                    </select>
                </div>
                <div>
                    <label for="movimiento-cantidad" class="etiqueta">Cantidad</label>
                    <input id="movimiento-cantidad" type="number" min="1" wire:model="movimiento_cantidad" class="control h-9 font-mono">
                    @error('movimiento_cantidad') <p class="error-campo">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex gap-2">
                <x-boton variante="primario" wire:click="guardarMovimiento">Registrar</x-boton>
                <x-boton variante="fantasma" wire:click="$set('panel', '')">Cancelar</x-boton>
            </div>
        </section>
    @endif

    <x-tabla minimo="760px">
        <thead>
            <tr>
                <th>Material</th>
                <th>Categoría</th>
                <th class="text-right">Existencia</th>
                <th class="text-right">Mínimo</th>
                <th class="text-right">Precio</th>
                <th class="text-right"><span class="sr-only">Acciones</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($materiales as $material)
                @php $bajo = $material->cantidad_total <= $material->cantidad_minima; @endphp
                <tr wire:key="material-{{ $material->id }}">
                    <td>
                        <a href="{{ route('inventario.show', $material) }}" wire:navigate class="font-medium text-tinta no-underline hover:text-acento-texto hover:underline">
                            {{ $material->nombre }}
                        </a>
                    </td>
                    <td class="text-tinta-2">{{ ucfirst($material->tipo ?? '—') }}</td>
                    <td class="text-right">
                        @if ($bajo)
                            <span class="inline-flex items-center gap-1.5 rounded bg-sla-atencion-fondo px-1.5 font-mono font-semibold text-sla-atencion" title="Stock bajo">
                                <x-sla-glifo estado="atencion" :tamano="10" />
                                {{ number_format($material->cantidad_total) }}
                            </span>
                            <span class="sr-only">stock bajo</span>
                        @else
                            <span class="font-mono font-semibold">{{ number_format($material->cantidad_total) }}</span>
                        @endif
                    </td>
                    <td class="text-right font-mono text-tinta-2">{{ number_format($material->cantidad_minima) }}</td>
                    <td class="text-right font-mono text-tinta-2">
                        {{ $material->precio_unitario ? '$'.number_format($material->precio_unitario, 0, ',', '.') : '—' }}
                    </td>
                    <td class="whitespace-nowrap text-right">
                        <x-boton tamano="chico" wire:click="moverStock({{ $material->id }})">Movimiento</x-boton>
                        <x-boton variante="fantasma" tamano="chico" wire:click="editarMaterial({{ $material->id }})">Editar</x-boton>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="!h-24 text-center text-tinta-3">No hay materiales registrados.</td>
                </tr>
            @endforelse
        </tbody>
    </x-tabla>

    <div>{{ $materiales->links() }}</div>
</div>
