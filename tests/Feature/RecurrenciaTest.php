<?php

use App\Models\Cliente;
use App\Models\Soporte;

/** Crea N casos para el mismo cliente, espaciados los días que se indiquen. */
function casosDelMismoCliente(int $cantidad, int $diasEntre, ?Cliente $cliente = null): Cliente
{
    $cliente ??= clienteDePrueba();

    foreach (range(1, $cantidad) as $i) {
        $caso = soporteDePrueba(['cliente_id' => $cliente->id]);

        $caso->forceFill([
            'created_at' => now()->subDays(($cantidad - $i) * $diasEntre),
        ])->saveQuietly();
    }

    return $cliente->refresh();
}

describe('reincidencia vista desde el caso', function () {
    it('cuenta los reportes previos dentro de la ventana', function () {
        $cliente = casosDelMismoCliente(3, diasEntre: 10);

        $ultimo = Soporte::where('cliente_id', $cliente->id)->latest('created_at')->first();

        expect($ultimo->reportesPreviosDelAbonado())->toBe(2)
            ->and($ultimo->esReincidente())->toBeTrue();
    });

    it('no cuenta los reportes que quedaron fuera de la ventana', function () {
        // 100 días entre casos: nada cae dentro de los 60 previos.
        $cliente = casosDelMismoCliente(3, diasEntre: 100);

        $ultimo = Soporte::where('cliente_id', $cliente->id)->latest('created_at')->first();

        expect($ultimo->reportesPreviosDelAbonado())->toBe(0)
            ->and($ultimo->esReincidente())->toBeFalse();
    });

    it('no cuenta los casos posteriores, solo los anteriores', function () {
        $cliente = casosDelMismoCliente(3, diasEntre: 5);

        $primero = Soporte::where('cliente_id', $cliente->id)->oldest('created_at')->first();

        expect($primero->reportesPreviosDelAbonado())->toBe(0);
    });

    it('no se cuenta a sí mismo', function () {
        $cliente = casosDelMismoCliente(1, diasEntre: 0);
        $unico = Soporte::where('cliente_id', $cliente->id)->first();

        expect($unico->reportesPreviosDelAbonado())->toBe(0);
    });
});

describe('lista de recurrentes', function () {
    it('deja fuera a quien no alcanza el mínimo', function () {
        casosDelMismoCliente(2, diasEntre: 5);

        expect(Cliente::recurrentes(12, 3)->get())->toHaveCount(0);
    });

    it('incluye a quien lo alcanza, con sus cifras', function () {
        $cliente = casosDelMismoCliente(4, diasEntre: 5);

        $fila = Cliente::recurrentes(12, 3)->get()->firstWhere('id', $cliente->id);

        expect($fila)->not->toBeNull()
            ->and($fila->reportes)->toBe(4)
            ->and($fila->ultimo_reporte)->not->toBeNull();
    });

    it('distingue la falla recurrente de la falta de contacto', function () {
        $conFalla = casosDelMismoCliente(3, diasEntre: 5);

        $sinContacto = casosDelMismoCliente(3, diasEntre: 5);
        Soporte::where('cliente_id', $sinContacto->id)
            ->update(['estado' => Soporte::ESTADO_CERRADO_SIN_CONTACTO]);

        expect($conFalla->motivoRecurrencia())->toBe(Cliente::MOTIVO_FALLA)
            ->and($sinContacto->refresh()->motivoRecurrencia())->toBe(Cliente::MOTIVO_SIN_CONTACTO);
    });

    it('marca como recurrente a quien supera el umbral anual', function () {
        $recurrente = casosDelMismoCliente(3, diasEntre: 20);
        $ocasional = casosDelMismoCliente(1, diasEntre: 0);

        expect($recurrente->esRecurrente())->toBeTrue()
            ->and($ocasional->esRecurrente())->toBeFalse();
    });
});
