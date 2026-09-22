<?php

use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
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

    // Material
    public ?int $material_id = null;
    public string $nombre = '';
    public string $tipo = 'cable';
    public string $cantidad_minima = '0';
    public string $precio_unitario = '';

    // Movimiento
    public ?int $movimiento_material_id = null;
    public string $movimiento_tipo = 'entrada';
    public string $movimiento_cantidad = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->tieneRol(User::ROL_ADMIN, User::ROL_GERENTE), 403);
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

        $this->material_id = $material->id;
        $this->nombre = $material->nombre;
        $this->tipo = $material->tipo ?? 'cable';
        $this->cantidad_minima = (string) $material->cantidad_minima;
        $this->precio_unitario = (string) $material->precio_unitario;
        $this->panel = 'material';
    }

    public function guardarMaterial(): void
    {
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
        $this->movimiento_material_id = $id;
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
        $cantidad = (int) $this->movimiento_cantidad;

        if ($this->movimiento_tipo === 'salida' && $material->cantidad_total < $cantidad) {
            $this->addError('movimiento_cantidad', "Solo hay {$material->cantidad_total} unidades disponibles.");
            return;
        }

        DB::transaction(function () use ($material, $cantidad) {
            $this->movimiento_tipo === 'entrada'
                ? $material->increment('cantidad_total', $cantidad)
                : $material->decrement('cantidad_total', $cantidad);

            MovimientoInventario::create([
                'material_id' => $material->id,
                'tipo' => $this->movimiento_tipo,
                'cantidad' => $cantidad,
            ]);
        });

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

<div class="p-6">
    @if (session('mensaje'))
        <div class="bg-green-100 text-green-800 p-3 rounded mb-4">{{ session('mensaje') }}</div>
    @endif

    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold text-slate-800">Inventario</h1>
        <button wire:click="nuevoMaterial" class="bg-blue-600 text-white px-4 py-2 rounded">+ Nuevo material</button>
    </div>

    @if ($this->bajos > 0)
        <div class="bg-orange-50 border border-orange-200 text-orange-900 p-3 rounded mb-4 text-sm flex justify-between items-center">
            <span>
                <strong>{{ $this->bajos }}</strong>
                {{ $this->bajos === 1 ? 'material está' : 'materiales están' }} en o por debajo del stock mínimo.
            </span>
            <button wire:click="$set('soloBajos', true)" class="underline">Ver solo esos</button>
        </div>
    @endif

    <div class="bg-white border rounded p-4 mb-4 grid gap-3 md:grid-cols-4 items-end">
        <div class="md:col-span-3">
            <label class="block text-xs font-medium text-slate-600 mb-1">Buscar</label>
            <input type="search" wire:model.live.debounce.400ms="buscar" class="w-full border rounded p-2 text-sm"
                placeholder="Nombre del material">
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" wire:model.live="soloBajos" class="rounded border-slate-300">
            Solo stock bajo
        </label>
    </div>

    {{-- Panel de material --}}
    @if ($panel === 'material')
        <div class="bg-white border rounded p-4 mb-4 space-y-3">
            <h2 class="font-semibold text-slate-800">{{ $material_id ? 'Editar material' : 'Nuevo material' }}</h2>

            <div class="grid gap-3 md:grid-cols-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 mb-1">Nombre</label>
                    <input type="text" wire:model="nombre" class="w-full border rounded p-2 text-sm">
                    @error('nombre') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Categoría</label>
                    <select wire:model="tipo" class="w-full border rounded p-2 text-sm">
                        <option value="cable">Cable</option>
                        <option value="conector">Conector</option>
                        <option value="router">Router / ONU</option>
                        <option value="equipo">Equipo</option>
                        <option value="accesorio">Accesorio</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Stock mínimo</label>
                    <input type="number" min="0" wire:model="cantidad_minima" class="w-full border rounded p-2 text-sm">
                    @error('cantidad_minima') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Precio unitario</label>
                    <input type="number" step="1" wire:model="precio_unitario" class="w-full border rounded p-2 text-sm">
                    @error('precio_unitario') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </div>
            </div>

            <p class="text-xs text-slate-500">
                La existencia no se edita aquí: entra y sale por movimientos, para que el kardex siempre cuadre.
            </p>

            <div class="flex gap-2">
                <button wire:click="guardarMaterial" class="bg-blue-600 text-white px-4 py-2 rounded text-sm">Guardar</button>
                <button wire:click="$set('panel', '')" class="px-4 py-2 rounded border text-sm">Cancelar</button>
            </div>
        </div>
    @endif

    {{-- Panel de movimiento --}}
    @if ($panel === 'movimiento')
        <div class="bg-white border rounded p-4 mb-4 space-y-3">
            <h2 class="font-semibold text-slate-800">Registrar movimiento</h2>

            <div class="grid gap-3 md:grid-cols-3">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Tipo</label>
                    <select wire:model="movimiento_tipo" class="w-full border rounded p-2 text-sm">
                        <option value="entrada">Entrada (compra o devolución a bodega)</option>
                        <option value="salida">Salida (baja o pérdida)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Cantidad</label>
                    <input type="number" min="1" wire:model="movimiento_cantidad" class="w-full border rounded p-2 text-sm">
                    @error('movimiento_cantidad') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="flex gap-2">
                <button wire:click="guardarMovimiento" class="bg-blue-600 text-white px-4 py-2 rounded text-sm">Registrar</button>
                <button wire:click="$set('panel', '')" class="px-4 py-2 rounded border text-sm">Cancelar</button>
            </div>
        </div>
    @endif

    <div class="overflow-x-auto bg-white border rounded shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-700 text-white">
                <tr>
                    <th class="p-2 text-left font-semibold">Material</th>
                    <th class="p-2 text-left font-semibold">Categoría</th>
                    <th class="p-2 text-right font-semibold">Existencia</th>
                    <th class="p-2 text-right font-semibold">Mínimo</th>
                    <th class="p-2 text-right font-semibold">Precio</th>
                    <th class="p-2 text-right font-semibold">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse ($materiales as $material)
                    @php $bajo = $material->cantidad_total <= $material->cantidad_minima; @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="p-2">
                            <a href="{{ route('inventario.show', $material) }}" wire:navigate class="text-slate-800 hover:text-blue-600">
                                {{ $material->nombre }}
                            </a>
                        </td>
                        <td class="p-2 text-slate-600 text-xs">{{ ucfirst($material->tipo ?? '—') }}</td>
                        <td class="p-2 text-right">
                            <span @class(['font-semibold', 'text-orange-700' => $bajo])>
                                {{ number_format($material->cantidad_total) }}
                            </span>
                            @if ($bajo)
                                <span class="block text-[10px] text-orange-700">stock bajo</span>
                            @endif
                        </td>
                        <td class="p-2 text-right text-slate-600">{{ number_format($material->cantidad_minima) }}</td>
                        <td class="p-2 text-right text-slate-600">
                            {{ $material->precio_unitario ? '$'.number_format($material->precio_unitario, 0, ',', '.') : '—' }}
                        </td>
                        <td class="p-2 text-right whitespace-nowrap">
                            <button wire:click="moverStock({{ $material->id }})" class="text-xs text-blue-600">Movimiento</button>
                            <button wire:click="editarMaterial({{ $material->id }})" class="ml-3 text-xs text-blue-600">Editar</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-6 text-center text-slate-500">No hay materiales registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $materiales->links() }}</div>
</div>
