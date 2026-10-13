<?php

use App\Models\Contrato;
use App\Models\EquipoInstalado;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Volt\Volt;

/*
 * La ficha de un abonado no puede tocar los contratos ni los equipos de otro,
 * aunque el navegador mande los ids que quiera.
 */

function contratoDe($cliente, string $numero): Contrato
{
    return Contrato::create([
        'cliente_id' => $cliente->id,
        'numero_contrato' => $numero,
        'fecha_inicio' => today()->subYear(),
        'estado' => 'activo',
    ]);
}

beforeEach(function () {
    $this->actingAs(usuarioCon(User::ROL_CALL_CENTER));
    $this->propio = clienteDePrueba();
    $this->ajeno = clienteDePrueba();
    $this->contratoAjeno = contratoDe($this->ajeno, 'CT-AJENO');
});

it('el navegador no puede cambiar el id del contrato que se edita', function () {
    Volt::test('clientes.show', ['cliente' => $this->propio])
        ->set('contrato_id', $this->contratoAjeno->id);
})->throws(CannotUpdateLockedPropertyException::class);

it('el navegador no puede cambiar el contrato al que se le instala el equipo', function () {
    Volt::test('clientes.show', ['cliente' => $this->propio])
        ->set('equipo_contrato_id', $this->contratoAjeno->id);
})->throws(CannotUpdateLockedPropertyException::class);

it('no abre el formulario de equipo sobre el contrato de otro abonado', function () {
    Volt::test('clientes.show', ['cliente' => $this->propio])
        ->call('nuevoEquipo', $this->contratoAjeno->id);
})->throws(ModelNotFoundException::class);

it('no retira un equipo instalado en el contrato de otro abonado', function () {
    $equipo = EquipoInstalado::create([
        'contrato_id' => $this->contratoAjeno->id, 'tipo' => 'router', 'estado' => 'activo',
    ]);

    expect(fn () => Volt::test('clientes.show', ['cliente' => $this->propio])->call('retirarEquipo', $equipo->id))
        ->toThrow(ModelNotFoundException::class);

    expect($equipo->refresh()->estado)->toBe('activo');
});

it('editar un contrato propio no lo muda de abonado ni toca los ajenos', function () {
    $propio = contratoDe($this->propio, 'CT-PROPIO');

    Volt::test('clientes.show', ['cliente' => $this->propio])
        ->call('editarContrato', $propio->id)
        ->set('valor_contrato', '99000')
        ->call('guardarContrato')
        ->assertHasNoErrors();

    expect($propio->refresh()->valor_contrato)->toEqual(99000)
        ->and($this->contratoAjeno->refresh()->cliente_id)->toBe($this->ajeno->id);
});
