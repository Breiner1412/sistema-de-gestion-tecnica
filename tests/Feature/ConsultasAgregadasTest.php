<?php

use App\Models\Cliente;
use App\Models\OrdenTrabajo;
use App\Models\Soporte;
use App\Models\Tecnico;
use App\Models\User;
use App\Support\MetricasTecnico;
use Livewire\Volt\Volt;

/** Un cliente con N casos; los primeros $sinContacto quedan cerrados sin contacto. */
function clienteConCasos(int $casos, int $sinContacto = 0): Cliente
{
    $cliente = clienteDePrueba();

    foreach (range(1, $casos) as $i) {
        $caso = soporteDePrueba(['cliente_id' => $cliente->id]);

        if ($i <= $sinContacto) {
            $caso->forceFill(['estado' => Soporte::ESTADO_CERRADO_SIN_CONTACTO])->saveQuietly();
        }
    }

    return $cliente;
}

it('el filtro por motivo se aplica antes de paginar: el total cuadra', function () {
    $this->actingAs(usuarioCon(User::ROL_GERENTE));

    $falla = clienteConCasos(3);
    $sinContacto = clienteConCasos(3, sinContacto: 2);

    $pagina = Volt::test('clientes.recurrentes')
        ->set('motivo', Cliente::MOTIVO_SIN_CONTACTO)
        ->viewData('clientes');

    expect($pagina->total())->toBe(1)
        ->and($pagina->getCollection()->pluck('id')->all())->toBe([$sinContacto->id]);

    $todos = Volt::test('clientes.recurrentes')->viewData('clientes');

    expect($todos->total())->toBe(2)
        ->and($todos->getCollection()->firstWhere('id', $falla->id)->reportes)->toEqual(3);
});

it('no cuenta los casos borrados al decidir quién es recurrente', function () {
    $cliente = clienteConCasos(3);
    Soporte::where('cliente_id', $cliente->id)->first()->delete(); // borrado lógico

    expect(Cliente::recurrentes(12, 3)->pluck('clientes.id')->all())->toBe([]);
});

it('el panel no fusiona a dos técnicos con el mismo nombre', function () {
    $this->actingAs(usuarioCon(User::ROL_GERENTE));

    foreach (range(1, 2) as $i) {
        $tecnico = usuarioCon(User::ROL_TECNICO_SOPORTE, 'Andrés Arango');
        soporteDePrueba()->asignarTecnicoSoporte($tecnico->id);
    }

    $filas = Volt::test('dashboard.index')->viewData('porTecnico');

    expect($filas->where('name', 'Andrés Arango'))->toHaveCount(2);
});

it('las métricas del mes cuentan bien escalados, inmediatos y enviados a campo', function () {
    $tecnico = usuarioCon(User::ROL_TECNICO_SOPORTE);
    $campo = usuarioCon(User::ROL_TECNICO_CAMPO);
    $ficha = Tecnico::create(['user_id' => $campo->id, 'nombre' => $campo->name, 'estado' => 'activo']);

    $conVisita = soporteDePrueba(['tecnico_soporte_id' => $tecnico->id, 'tipo_solicitud' => Soporte::TIPO_SIN_INTERNET]);
    $conVisitaBorrada = soporteDePrueba(['tecnico_soporte_id' => $tecnico->id]);
    soporteDePrueba(['tecnico_soporte_id' => $tecnico->id, 'escalado_nivel_3' => true]);

    $orden = fn ($caso) => OrdenTrabajo::create([
        'soporte_id' => $caso->id, 'tecnico_id' => $ficha->id, 'tipo' => 'revision',
        'estado' => OrdenTrabajo::ESTADO_PROGRAMADA, 'fecha_programada' => today(),
    ]);
    $orden($conVisita);
    $orden($conVisitaBorrada)->delete();

    $cifras = MetricasTecnico::para($tecnico, now()->year, now()->month)->calcular();

    expect($cifras['total'])->toBe(3)
        ->and($cifras['enviados_campo'])->toBe(1)
        ->and($cifras['escalados'])->toBe(1)
        ->and($cifras['inmediatos'])->toBe(1);
});
