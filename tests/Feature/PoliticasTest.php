<?php

use App\Models\Inventario;
use App\Models\Soporte;
use App\Models\Tecnico;
use App\Models\User;
use Livewire\Volt\Volt;

/*
 * Cada prueba es una puerta que antes estaba abierta. Las acciones se llaman
 * por Livewire, que es por donde entra quien manipula la página: el middleware
 * de la ruta no vuelve a correr en /livewire/update.
 */

/** Un caso en proceso con su responsable de nivel 2. */
function casoDe(User $tecnico): Soporte
{
    $caso = soporteDePrueba();
    $caso->asignarTecnicoSoporte($tecnico->id);

    return $caso->refresh();
}

describe('casos', function () {
    it('el primer nivel no puede resolver un caso del nivel 2', function () {
        $caso = casoDe(usuarioCon(User::ROL_TECNICO_SOPORTE));
        $this->actingAs(usuarioCon(User::ROL_CALL_CENTER));

        Volt::test('soportes.show', ['soporte' => $caso])
            ->set('diagnostico_id', diagnosticoDePrueba()->id)
            ->set('observaciones_cierre', 'Lo cierro aunque no es mío.')
            ->call('resolver')
            ->assertForbidden();

        expect($caso->refresh()->estado)->toBe(Soporte::ESTADO_EN_PROCESO);
    });

    it('un técnico de soporte no trabaja el caso de otro técnico', function (string $accion) {
        $caso = casoDe(usuarioCon(User::ROL_TECNICO_SOPORTE));
        $this->actingAs(usuarioCon(User::ROL_TECNICO_SOPORTE));

        Volt::test('soportes.show', ['soporte' => $caso])->call($accion)->assertForbidden();
    })->with(['sinContacto', 'seguimiento', 'escalar', 'programarVisita']);

    it('el responsable sí trabaja su caso', function () {
        $tecnico = usuarioCon(User::ROL_TECNICO_SOPORTE);
        $caso = casoDe($tecnico);
        $this->actingAs($tecnico);

        Volt::test('soportes.show', ['soporte' => $caso])->call('seguimiento')->assertHasNoErrors();

        expect($caso->refresh()->estado)->toBe(Soporte::ESTADO_SEGUIMIENTO);
    });

    it('el primer nivel cancela lo que registró mientras nadie lo toma, y nada más', function () {
        $asesor = usuarioCon(User::ROL_CALL_CENTER);
        $suyo = soporteDePrueba(['usuario_registra_id' => $asesor->id]);
        $ajeno = soporteDePrueba();
        $this->actingAs($asesor);

        Volt::test('soportes.show', ['soporte' => $ajeno])
            ->set('motivo_cancelacion', 'El cliente desiste.')
            ->call('cancelar')
            ->assertForbidden();

        Volt::test('soportes.show', ['soporte' => $suyo])
            ->set('motivo_cancelacion', 'El cliente desiste.')
            ->call('cancelar')
            ->assertHasNoErrors();

        expect($suyo->refresh()->estado)->toBe(Soporte::ESTADO_CANCELADO)
            ->and($ajeno->refresh()->estado)->toBe(Soporte::ESTADO_PENDIENTE);
    });

    it('solo el ingeniero al que se escaló devuelve el caso desde redes', function () {
        $tecnico = usuarioCon(User::ROL_TECNICO_SOPORTE);
        $asignado = usuarioCon(User::ROL_INGENIERO_REDES);
        $caso = casoDe($tecnico);
        $caso->escalarANivel3($asignado->id, 'Corte en el troncal de la zona.', $tecnico->id);

        $this->actingAs(usuarioCon(User::ROL_INGENIERO_REDES));
        Volt::test('soportes.show', ['soporte' => $caso->refresh()])
            ->set('respuesta_n3', 'Se reinició la OLT de la zona.')
            ->call('devolverDeRedes')
            ->assertForbidden();

        $this->actingAs($asignado);
        Volt::test('soportes.show', ['soporte' => $caso->refresh()])
            ->set('respuesta_n3', 'Se reinició la OLT de la zona.')
            ->call('devolverDeRedes')
            ->assertHasNoErrors();

        expect($caso->refresh()->estado)->toBe(Soporte::ESTADO_EN_PROCESO);
    });

    it('redes no puede crear casos', function () {
        $this->actingAs(usuarioCon(User::ROL_INGENIERO_REDES));

        $this->get(route('soportes.create'))->assertForbidden();
    });
});

describe('visitas', function () {
    it('un técnico de campo no abre la visita de otro', function () {
        $visita = visitaDePrueba();
        $otro = usuarioCon(User::ROL_TECNICO_CAMPO);
        Tecnico::create(['user_id' => $otro->id, 'nombre' => $otro->name, 'estado' => 'activo']);

        $this->actingAs($otro)->get(route('ordenes.show', $visita))->assertForbidden();
    });

    it('un técnico de campo sin ficha de técnico no ve las visitas de nadie', function () {
        visitaDePrueba();
        $this->actingAs(usuarioCon(User::ROL_TECNICO_CAMPO));

        Volt::test('ordenes.index')->assertViewHas('ordenes', fn ($ordenes) => $ordenes->total() === 0);
    });

    it('el técnico sí abre su propia visita', function () {
        $usuario = usuarioCon(User::ROL_TECNICO_CAMPO);
        $visita = visitaDePrueba($usuario);

        $this->actingAs($usuario)->get(route('ordenes.show', $visita))->assertOk();
    });
});

describe('informes', function () {
    it('un técnico no ve el listado de informes de gestión, solo los suyos', function () {
        $companero = usuarioCon(User::ROL_TECNICO_SOPORTE, 'Compañero de equipo');
        $this->actingAs(usuarioCon(User::ROL_TECNICO_SOPORTE));

        $this->get(route('informes.index'))
            ->assertOk()
            ->assertSee('Mis informes')
            ->assertDontSee('Informes de rendimiento')
            ->assertDontSee($companero->name);
    });
});

describe('acciones que solo comprobaban el rol al abrir la pantalla', function () {
    it('inventario: quien pierde el rol con la pantalla abierta ya no mueve stock', function () {
        $gerente = usuarioCon(User::ROL_GERENTE);
        $material = Inventario::create(['nombre' => 'Roseta óptica', 'tipo' => 'accesorio', 'cantidad_total' => 10]);
        $this->actingAs($gerente);

        $pantalla = Volt::test('inventario.index')
            ->call('moverStock', $material->id)
            ->set('movimiento_tipo', 'salida')
            ->set('movimiento_cantidad', '4');

        $gerente->update(['rol' => User::ROL_CALL_CENTER]);

        $pantalla->call('guardarMovimiento')->assertForbidden();
        expect($material->refresh()->cantidad_total)->toBe(10);
    });

    it('usuarios: quien pierde el rol de admin ya no activa ni desactiva cuentas', function () {
        $admin = usuarioCon(User::ROL_ADMIN);
        $otro = usuarioCon(User::ROL_CALL_CENTER);
        $this->actingAs($admin);

        $pantalla = Volt::test('usuarios.index');
        $admin->update(['rol' => User::ROL_GERENTE]);

        $pantalla->call('alternarEstado', $otro->id)->assertForbidden();
        expect($otro->refresh()->estado)->toBe('activo');
    });

    it('clientes: quien pierde el rol con el formulario abierto ya no guarda', function () {
        $asesor = usuarioCon(User::ROL_CALL_CENTER);
        $this->actingAs($asesor);

        $formulario = Volt::test('clientes.form')
            ->set('cedula', '1099911111')
            ->set('nombre', 'Cliente nuevo');

        $asesor->update(['rol' => User::ROL_INGENIERO_REDES]);

        $formulario->call('guardar')->assertForbidden();
    });
});
