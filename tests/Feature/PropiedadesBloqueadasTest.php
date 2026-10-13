<?php

use App\Models\Contrato;
use App\Models\Inventario;
use App\Models\Soporte;
use App\Models\User;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Volt\Volt;

/*
 * Ids que pone el servidor y que el navegador no debe poder reescribir entre
 * una petición de Livewire y la siguiente.
 */

it('el alta de casos no deja cambiar el cliente por fuera del buscador', function () {
    $this->actingAs(usuarioCon(User::ROL_CALL_CENTER));

    Volt::test('soportes.create')->set('cliente_id', clienteDePrueba()->id);
})->throws(CannotUpdateLockedPropertyException::class);

it('el caso no se puede amarrar al contrato de otro cliente', function () {
    $this->actingAs(usuarioCon(User::ROL_CALL_CENTER));
    $cliente = clienteDePrueba();
    $ajeno = Contrato::create([
        'cliente_id' => clienteDePrueba()->id, 'numero_contrato' => 'CT-AJENO',
        'fecha_inicio' => today()->subYear(), 'estado' => 'activo',
    ]);

    Volt::test('soportes.create')
        ->call('seleccionarCliente', $cliente->id)
        ->set('contrato_id', $ajeno->id)
        ->set('tipo_solicitud', Soporte::TIPO_SOPORTE_REMOTO)
        ->set('descripcion', 'El abonado reporta lentitud desde ayer.')
        ->call('guardar')
        ->assertHasErrors('contrato_id');
});

it('inventario: el navegador no cambia el material del panel abierto', function (string $propiedad) {
    $this->actingAs(usuarioCon(User::ROL_GERENTE));
    $material = Inventario::create(['nombre' => 'Conector SC/APC', 'tipo' => 'conector', 'cantidad_total' => 5]);

    Volt::test('inventario.index')->set($propiedad, $material->id);
})->with(['material_id', 'movimiento_material_id'])
    ->throws(CannotUpdateLockedPropertyException::class);

it('inventario: no se abre el panel de movimiento de un material que no existe', function () {
    $this->actingAs(usuarioCon(User::ROL_GERENTE));

    Volt::test('inventario.index')->call('moverStock', 99999);
})->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
