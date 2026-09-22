<?php

use App\Exceptions\TransicionInvalidaException;
use App\Models\Soporte;
use App\Models\SoporteHistorialEstado;
use App\Models\User;

describe('numeración', function () {
    it('asigna un consecutivo legible al crear el caso', function () {
        $primero = soporteDePrueba();
        $segundo = soporteDePrueba();

        $anio = now()->year;

        expect($primero->numero_soporte)->toBe("SGT-{$anio}-000001")
            ->and($segundo->numero_soporte)->toBe("SGT-{$anio}-000002");
    });
});

describe('criticidad derivada', function () {
    it('marca inmediato un caso sin internet', function () {
        $caso = soporteDePrueba(['tipo_solicitud' => Soporte::TIPO_SIN_INTERNET]);

        expect($caso->criticidad)->toBe(Soporte::CRITICIDAD_INMEDIATA);
    });

    it('marca inmediato cuando la falla reportada es de servicio caído', function () {
        $caso = soporteDePrueba(['tipo_falla_id' => fallaDePrueba(critica: true)->id]);

        expect($caso->criticidad)->toBe(Soporte::CRITICIDAD_INMEDIATA);
    });

    it('deja en normal una falla de degradación', function () {
        $caso = soporteDePrueba(['tipo_falla_id' => fallaDePrueba(critica: false)->id]);

        expect($caso->criticidad)->toBe(Soporte::CRITICIDAD_NORMAL);
    });

    it('deja en normal los cambios de plan aunque no tengan falla', function () {
        $caso = soporteDePrueba(['tipo_solicitud' => Soporte::TIPO_CAMBIO_PLAN]);

        expect($caso->criticidad)->toBe(Soporte::CRITICIDAD_NORMAL);
    });

    it('da menos tiempo a un caso inmediato que a uno normal', function () {
        $inmediato = soporteDePrueba(['tipo_solicitud' => Soporte::TIPO_SIN_INTERNET]);
        $normal = soporteDePrueba();

        expect($inmediato->presupuestoSlaMinutos())
            ->toBeLessThan($normal->presupuestoSlaMinutos());
    });
});

describe('máquina de estados', function () {
    it('rechaza una transición que el flujo no permite', function () {
        $caso = soporteDePrueba();

        // Un caso recién ingresado no puede saltar directo a visita programada.
        expect(fn() => $caso->cambiarEstado(Soporte::ESTADO_ENVIADO_TECNICO))
            ->toThrow(TransicionInvalidaException::class);

        expect($caso->fresh()->estado)->toBe(Soporte::ESTADO_PENDIENTE);
    });

    it('no hace nada si el estado destino es el actual', function () {
        $caso = soporteDePrueba();
        $caso->cambiarEstado(Soporte::ESTADO_PENDIENTE);

        expect(SoporteHistorialEstado::where('soporte_id', $caso->id)->count())->toBe(0);
    });

    it('deja rastro de cada transición en el historial', function () {
        $caso = soporteDePrueba();
        $caso->cambiarEstado(Soporte::ESTADO_EN_PROCESO);
        $caso->cambiarEstado(Soporte::ESTADO_SEGUIMIENTO);

        $historial = SoporteHistorialEstado::where('soporte_id', $caso->id)
            ->orderBy('id')
            ->pluck('estado_nuevo')
            ->all();

        expect($historial)->toBe([Soporte::ESTADO_EN_PROCESO, Soporte::ESTADO_SEGUIMIENTO]);
    });

    it('marca como automática la transición que no hizo una persona', function () {
        $caso = soporteDePrueba();
        $caso->cambiarEstado(Soporte::ESTADO_EN_PROCESO);

        $registro = SoporteHistorialEstado::where('soporte_id', $caso->id)->first();

        expect($registro->automatico)->toBeTrue()
            ->and($registro->usuario_id)->toBeNull();
    });
});

describe('asignación', function () {
    it('pasa el caso a en proceso y congela el tiempo de respuesta', function () {
        $tecnico = usuarioCon(User::ROL_TECNICO_SOPORTE, 'Técnico N2');
        $caso = soporteDePrueba();

        $caso->asignarTecnicoSoporte($tecnico->id);
        $caso->refresh();

        expect($caso->estado)->toBe(Soporte::ESTADO_EN_PROCESO)
            ->and($caso->tecnico_soporte_id)->toBe($tecnico->id)
            ->and($caso->fecha_asignacion)->not->toBeNull()
            ->and($caso->tiempo_respuesta)->not->toBeNull();
    });

    it('no reescribe el tiempo de respuesta al reasignar', function () {
        $primero = usuarioCon(User::ROL_TECNICO_SOPORTE);
        $segundo = usuarioCon(User::ROL_TECNICO_SOPORTE);

        $caso = soporteDePrueba();
        $caso->asignarTecnicoSoporte($primero->id);
        $asignacionOriginal = $caso->fresh()->fecha_asignacion;

        $caso->asignarTecnicoSoporte($segundo->id);
        $caso->refresh();

        expect($caso->tecnico_soporte_id)->toBe($segundo->id)
            ->and($caso->fecha_asignacion->timestamp)->toBe($asignacionOriginal->timestamp);
    });
});

describe('reloj de SLA', function () {
    it('arranca al crear el caso', function () {
        $caso = soporteDePrueba();

        expect($caso->sla_vence_at)->not->toBeNull()
            ->and($caso->slaPausado())->toBeFalse();
    });

    it('se detiene al escalar a redes y se reanuda al volver', function () {
        $redes = usuarioCon(User::ROL_INGENIERO_REDES);
        $caso = soporteDePrueba();
        $caso->cambiarEstado(Soporte::ESTADO_EN_PROCESO);

        $caso->escalarANivel3($redes->id, 'Se sospecha corte en el troncal del sector.');
        $caso->refresh();

        expect($caso->estado)->toBe(Soporte::ESTADO_ESCALADO_N3)
            ->and($caso->slaPausado())->toBeTrue()
            ->and($caso->sla_minutos_restantes)->toBeGreaterThan(0)
            ->and($caso->escalado_nivel_3)->toBeTrue()
            ->and($caso->escalado_a_id)->toBe($redes->id);

        $caso->devolverDeNivel3('Se reparó el empalme en el poste, validar con el usuario.');
        $caso->refresh();

        expect($caso->estado)->toBe(Soporte::ESTADO_EN_PROCESO)
            ->and($caso->slaPausado())->toBeFalse()
            ->and($caso->sla_minutos_restantes)->toBeNull()
            ->and($caso->respuesta_n3)->not->toBeNull();
    });

    it('deja de correr una vez el caso está cerrado', function () {
        $caso = soporteDePrueba();
        $caso->cambiarEstado(Soporte::ESTADO_EN_PROCESO);
        $caso->cerrar(diagnosticoDePrueba()->id, 'Se corrigieron los DNS del equipo.');
        $caso->refresh();

        expect($caso->minutosRestantesSla())->toBe(0)
            ->and($caso->slaVencido())->toBeFalse()
            ->and($caso->semaforoSla())->toBe('ok');
    });
});

describe('cierre', function () {
    it('guarda diagnóstico, fecha y tiempo de resolución', function () {
        $diagnostico = diagnosticoDePrueba();
        $caso = soporteDePrueba();
        $caso->cambiarEstado(Soporte::ESTADO_EN_PROCESO);

        $caso->cerrar($diagnostico->id, 'Se reinició la ONU y el servicio quedó estable.');
        $caso->refresh();

        expect($caso->estado)->toBe(Soporte::ESTADO_SOLUCIONADO)
            ->and($caso->diagnostico_id)->toBe($diagnostico->id)
            ->and($caso->fecha_cierre)->not->toBeNull()
            ->and($caso->tiempo_resolucion)->not->toBeNull()
            ->and($caso->estaCerrado())->toBeTrue();
    });

    it('no permite mover un caso cancelado', function () {
        $caso = soporteDePrueba();
        $caso->cambiarEstado(Soporte::ESTADO_CANCELADO, 'El usuario informa que ya funciona.');

        expect(fn() => $caso->cambiarEstado(Soporte::ESTADO_EN_PROCESO))
            ->toThrow(TransicionInvalidaException::class);
    });
});

describe('sin contacto', function () {
    it('cuenta el intento y programa el siguiente', function () {
        $caso = soporteDePrueba();
        $caso->cambiarEstado(Soporte::ESTADO_EN_PROCESO);

        $caso->marcarSinContacto();
        $caso->refresh();

        expect($caso->estado)->toBe(Soporte::ESTADO_SIN_CONTACTO)
            ->and($caso->intentos_contacto)->toBe(1)
            ->and($caso->proximo_intento_at)->not->toBeNull()
            ->and($caso->slaPausado())->toBeTrue();
    });

    it('cierra el caso al agotar los intentos permitidos', function () {
        config(['sla.sin_contacto.max_intentos' => 3]);

        $caso = soporteDePrueba();
        $caso->cambiarEstado(Soporte::ESTADO_EN_PROCESO);

        $caso->marcarSinContacto();
        $caso->cambiarEstado(Soporte::ESTADO_EN_PROCESO);
        $caso->marcarSinContacto();
        $caso->cambiarEstado(Soporte::ESTADO_EN_PROCESO);
        $caso->marcarSinContacto();

        $caso->refresh();

        expect($caso->intentos_contacto)->toBe(3)
            ->and($caso->estado)->toBe(Soporte::ESTADO_CERRADO_SIN_CONTACTO)
            ->and($caso->estaCerrado())->toBeTrue();
    });
});
