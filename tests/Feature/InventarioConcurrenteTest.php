<?php

use App\Models\Inventario;
use App\Models\MaterialOrden;
use App\Models\MovimientoInventario;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;

function materialDePrueba(int $stock): Inventario
{
    return Inventario::create(['nombre' => 'ONU GPON '.uniqid(), 'tipo' => 'router', 'cantidad_total' => $stock]);
}

it('quitar el mismo consumo dos veces a la vez devuelve el stock una sola vez', function () {
    $material = materialDePrueba(10);
    $consumo = MaterialOrden::create([
        'orden_id' => visitaDePrueba()->id, 'material_id' => $material->id, 'cantidad_usada' => 4,
    ]);
    expect($material->refresh()->cantidad_total)->toBe(6);

    // Dos clics (o dos pestañas) con el mismo consumo ya cargado.
    $clicA = MaterialOrden::find($consumo->id);
    $clicB = MaterialOrden::find($consumo->id);

    $clicA->delete();
    $clicB->delete();

    expect($material->refresh()->cantidad_total)->toBe(10)
        ->and(MovimientoInventario::where('tipo', 'devolucion')->count())->toBe(1);
});

it('una salida manual no deja el stock negativo aunque otro lo gaste en medio', function () {
    $this->actingAs(usuarioCon(User::ROL_GERENTE));
    $material = materialDePrueba(5);

    $pantalla = Volt::test('inventario.index')
        ->call('moverStock', $material->id)
        ->set('movimiento_tipo', 'salida')
        ->set('movimiento_cantidad', '5');

    // Justo después de que guardarMovimiento lee el material, otro se lleva 3.
    $armado = true;
    Inventario::retrieved(function (Inventario $leido) use ($material, &$armado) {
        if ($armado && $leido->id === $material->id) {
            $armado = false;
            DB::table('inventario')->where('id', $material->id)->decrement('cantidad_total', 3);
        }
    });

    $pantalla->call('guardarMovimiento')->assertHasErrors('movimiento_cantidad');

    expect($material->refresh()->cantidad_total)->toBe(2)
        ->and(MovimientoInventario::where('tipo', 'salida')->count())->toBe(0);
});

it('una salida con stock suficiente se aplica y deja su movimiento', function () {
    $this->actingAs(usuarioCon(User::ROL_GERENTE));
    $material = materialDePrueba(5);

    Volt::test('inventario.index')
        ->call('moverStock', $material->id)
        ->set('movimiento_tipo', 'salida')
        ->set('movimiento_cantidad', '5')
        ->call('guardarMovimiento')
        ->assertHasNoErrors();

    expect($material->refresh()->cantidad_total)->toBe(0)
        ->and(MovimientoInventario::where('tipo', 'salida')->count())->toBe(1);
});
