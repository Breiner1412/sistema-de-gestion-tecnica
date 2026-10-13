<?php

use App\Exceptions\TransicionInvalidaException;
use App\Models\Soporte;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('valida la transición contra el estado actual, no contra el de la pantalla', function () {
    $caso = soporteDePrueba();
    $caso->cambiarEstado(Soporte::ESTADO_EN_PROCESO);

    // Dos pantallas abiertas sobre el mismo caso.
    $pantallaA = Soporte::find($caso->id);
    $pantallaB = Soporte::find($caso->id);

    $pantallaA->cambiarEstado(Soporte::ESTADO_CANCELADO, 'El cliente desiste.');

    // B todavía lo ve en proceso; desde cancelado no se puede ir a seguimiento.
    expect(fn () => $pantallaB->cambiarEstado(Soporte::ESTADO_SEGUIMIENTO))
        ->toThrow(TransicionInvalidaException::class);

    expect($caso->refresh()->estado)->toBe(Soporte::ESTADO_CANCELADO);
});

it('no duplica el historial cuando dos pantallas hacen el mismo cambio', function () {
    $caso = soporteDePrueba();
    $caso->cambiarEstado(Soporte::ESTADO_EN_PROCESO);

    $pantallaA = Soporte::find($caso->id);
    $pantallaB = Soporte::find($caso->id);

    $pantallaA->cambiarEstado(Soporte::ESTADO_SEGUIMIENTO);
    $pantallaB->cambiarEstado(Soporte::ESTADO_SEGUIMIENTO);

    expect($caso->historialEstados()->where('estado_nuevo', Soporte::ESTADO_SEGUIMIENTO)->count())->toBe(1);
});

it('un caso movido a mano durante el reintento no frena a los demás', function () {
    $tecnico = usuarioCon(User::ROL_TECNICO_SOPORTE);
    $casos = collect(range(1, 2))->map(function () use ($tecnico) {
        $caso = soporteDePrueba();
        $caso->asignarTecnicoSoporte($tecnico->id);
        $caso->marcarSinContacto($tecnico->id);
        $caso->forceFill(['proximo_intento_at' => now()->subHour()])->saveQuietly();

        return $caso;
    });

    // Justo después de que el comando carga el primero, alguien lo cancela.
    $yaMovido = false;
    Soporte::retrieved(function (Soporte $leido) use ($casos, &$yaMovido) {
        if (! $yaMovido && $leido->id === $casos[0]->id) {
            $yaMovido = true;
            DB::table('soportes')->where('id', $leido->id)->update(['estado' => Soporte::ESTADO_CANCELADO]);
        }
    });

    $this->artisan('soportes:reintentar-contacto')->assertSuccessful();

    expect($casos[0]->refresh()->estado)->toBe(Soporte::ESTADO_CANCELADO)
        ->and($casos[1]->refresh()->estado)->toBe(Soporte::ESTADO_EN_PROCESO)
        ->and($casos[1]->proximo_intento_at)->toBeNull();
});
