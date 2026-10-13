<?php

use App\Models\Soporte;
use Carbon\CarbonImmutable;

/*
 * Un caso normal vence al cierre del quinto día hábil. El porcentaje del
 * semáforo tiene que medirse contra ese mismo plazo: antes se medía contra
 * 5 × 11 h = 3.300 minutos, aunque el plazo real fuera de 3.960, así que el
 * semáforo arrancaba quieto y avisaba el riesgo tarde.
 */

beforeEach(function () {
    config(['sla.festivos_extra' => []]);
});

it('el porcentaje se mide contra el plazo real hasta el vencimiento', function () {
    // Lunes 5 de octubre de 2026, 07:00: arranca la jornada.
    $this->travelTo(CarbonImmutable::parse('2026-10-05 07:00:00'));
    $caso = soporteDePrueba(['tipo_solicitud' => Soporte::TIPO_CAMBIO_PLAN]); // criticidad normal

    expect($caso->criticidad)->toBe(Soporte::CRITICIDAD_NORMAL)
        ->and($caso->sla_vence_at->format('Y-m-d H:i'))->toBe('2026-10-13 18:00') // el lunes 12 es festivo
        ->and($caso->porcentajeSla())->toBe(0);

    // Una jornada completa después: 660 de 3.960 minutos hábiles, un 17%.
    $this->travelTo(CarbonImmutable::parse('2026-10-05 18:00:00'));

    expect($caso->refresh()->porcentajeSla())->toBe(17);
});

it('la pausa no cambia el presupuesto del caso', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 07:00:00'));
    $caso = soporteDePrueba(['tipo_solicitud' => Soporte::TIPO_CAMBIO_PLAN]);
    $presupuesto = $caso->presupuestoSlaMinutos();

    $this->travelTo(CarbonImmutable::parse('2026-10-06 10:00:00'));
    $caso->pausarSla();
    $this->travelTo(CarbonImmutable::parse('2026-10-08 10:00:00'));
    $caso->reanudarSla();

    expect($caso->presupuestoSlaMinutos())->toBe($presupuesto);
});

it('un caso inmediato sigue midiéndose en horas hábiles', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00:00'));
    $caso = soporteDePrueba(['tipo_solicitud' => Soporte::TIPO_SIN_INTERNET]);

    expect($caso->presupuestoSlaMinutos())->toBe(240);

    $this->travelTo(CarbonImmutable::parse('2026-10-05 10:00:00'));

    expect($caso->refresh()->porcentajeSla())->toBe(50);
});
