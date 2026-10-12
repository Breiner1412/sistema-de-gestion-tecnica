<?php

use App\Support\CalendarioHabil;
use Carbon\CarbonImmutable;

describe('festivos de Colombia', function () {
    it('calcula los 18 festivos del año', function () {
        expect(CalendarioHabil::festivosColombia(2026))->toHaveCount(18)
            ->and(CalendarioHabil::festivosColombia(2027))->toHaveCount(18);
    });

    it('corre al lunes los festivos de la Ley Emiliani', function () {
        // Reyes cae martes 6 de enero de 2026, así que se celebra el lunes 12.
        $festivos = CalendarioHabil::festivosColombia(2026);

        expect($festivos)->not->toHaveKey('2026-01-06')
            ->and($festivos)->toHaveKey('2026-01-12');

        // Independencia de Cartagena cae miércoles 11 de noviembre.
        expect($festivos)->not->toHaveKey('2026-11-11')
            ->and($festivos)->toHaveKey('2026-11-16');
    });

    it('deja en su fecha los festivos fijos y los de Semana Santa', function () {
        $festivos = CalendarioHabil::festivosColombia(2026);

        expect($festivos)->toHaveKeys([
            '2026-01-01', // Año nuevo
            '2026-04-02', // Jueves Santo
            '2026-04-03', // Viernes Santo
            '2026-05-01', // Día del trabajo
            '2026-07-20', // Independencia
            '2026-12-25', // Navidad
        ]);
    });

    it('reconoce un festivo como día no hábil', function () {
        expect(calendarioDePrueba()->esDiaHabil(CarbonImmutable::parse('2026-10-12')))->toBeFalse() // Día de la Raza
            ->and(calendarioDePrueba()->esDiaHabil(CarbonImmutable::parse('2026-09-12')))->toBeFalse() // sábado
            ->and(calendarioDePrueba()->esDiaHabil(CarbonImmutable::parse('2026-09-15')))->toBeTrue(); // martes normal
    });
});

describe('aritmética de tiempo hábil', function () {
    it('salta el fin de semana al sumar horas', function () {
        // Viernes 16:00 + 4 horas hábiles: quedan 2 el viernes y 2 el lunes.
        $resultado = calendarioDePrueba()->sumarHoras(CarbonImmutable::parse('2026-09-11 16:00'), 4);

        expect($resultado->format('Y-m-d H:i'))->toBe('2026-09-14 09:00');
    });

    it('salta también los festivos', function () {
        // Viernes 17:00 + 2 horas: el lunes 12 es festivo, así que cae el martes.
        $resultado = calendarioDePrueba()->sumarHoras(CarbonImmutable::parse('2026-10-09 17:00'), 2);

        expect($resultado->format('Y-m-d H:i'))->toBe('2026-10-13 08:00');
    });

    it('mueve al inicio de la jornada lo que entra fuera de horario', function () {
        // Un caso que entra a las 22:00 empieza a contar al otro día a las 7:00.
        $resultado = calendarioDePrueba()->sumarHoras(CarbonImmutable::parse('2026-09-15 22:00'), 1);

        expect($resultado->format('Y-m-d H:i'))->toBe('2026-09-16 08:00');
    });

    it('vence cinco días hábiles al cierre de la jornada', function () {
        // Desde el lunes 14: martes, miércoles, jueves, viernes y lunes 21.
        $resultado = calendarioDePrueba()->sumarDias(CarbonImmutable::parse('2026-09-14 09:00'), 5);

        expect($resultado->format('Y-m-d H:i'))->toBe('2026-09-21 18:00');
    });

    it('no cuenta las horas fuera de jornada al medir un intervalo', function () {
        // Viernes 16:00 a lunes 09:00: 2 horas el viernes y 2 el lunes.
        $minutos = calendarioDePrueba()->minutosEntre(
            CarbonImmutable::parse('2026-09-11 16:00'),
            CarbonImmutable::parse('2026-09-14 09:00'),
        );

        expect($minutos)->toBe(240);
    });

    it('devuelve cero si el intervalo va al revés', function () {
        $minutos = calendarioDePrueba()->minutosEntre(
            CarbonImmutable::parse('2026-09-14 09:00'),
            CarbonImmutable::parse('2026-09-11 16:00'),
        );

        expect($minutos)->toBe(0);
    });

    it('cuenta una jornada completa como los minutos de la jornada', function () {
        $minutos = calendarioDePrueba()->minutosEntre(
            CarbonImmutable::parse('2026-09-15 07:00'),
            CarbonImmutable::parse('2026-09-15 18:00'),
        );

        expect($minutos)->toBe(11 * 60);
    });
});

it('mide la jornada en la hora local aunque la fecha llegue en UTC', function () {
    $zonaAnterior = date_default_timezone_get();
    date_default_timezone_set('America/Bogota');
    $calendario = new \App\Support\CalendarioHabil(7, 18, [1, 2, 3, 4, 5], []);

    // Martes 13 de octubre de 2026, 20:00 UTC = 15:00 en Bogotá: dentro de la jornada.
    $utc = \Carbon\CarbonImmutable::parse('2026-10-13 20:00:00', 'UTC');

    expect($calendario->siguienteHabil($utc)->format('Y-m-d H:i'))->toBe('2026-10-13 15:00')
        ->and($calendario->minutosEntre($utc, $utc->addHours(2)))->toBe(120);

    date_default_timezone_set($zonaAnterior);
});
