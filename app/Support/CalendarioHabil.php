<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Cuenta tiempo hábil: respeta la jornada laboral, los fines de semana y los
 * festivos de Colombia.
 *
 * Los 18 festivos se calculan, no se listan: seis son de fecha fija, siete se
 * corren al lunes siguiente por la Ley 51 de 1983 ("Ley Emiliani") y cinco
 * dependen de la Pascua.
 */
class CalendarioHabil
{
    /** Tope de seguridad para que ningún recorrido se vuelva infinito. */
    private const MAX_DIAS = 3650;

    /** @var array<int, array<string, true>> festivos ya calculados, por año */
    private static array $cacheFestivos = [];

    /**
     * @param  array<int, int>  $diasHabiles  días ISO (1 = lunes)
     * @param  array<int, string>  $festivosExtra  fechas 'Y-m-d'
     */
    public function __construct(
        private readonly int $horaInicio = 7,
        private readonly int $horaFin = 18,
        private readonly array $diasHabiles = [1, 2, 3, 4, 5],
        private readonly array $festivosExtra = [],
    ) {}

    public static function desdeConfig(): self
    {
        return new self(
            (int) config('sla.jornada.hora_inicio', 7),
            (int) config('sla.jornada.hora_fin', 18),
            (array) config('sla.jornada.dias', [1, 2, 3, 4, 5]),
            (array) config('sla.festivos_extra', []),
        );
    }

    /* ---------------------------------------------------------------
     | Consultas
     * --------------------------------------------------------------- */

    public function esFestivo(CarbonInterface $fecha): bool
    {
        $clave = $fecha->format('Y-m-d');

        return isset(static::festivosColombia((int) $fecha->year)[$clave])
            || in_array($clave, $this->festivosExtra, true);
    }

    public function esDiaHabil(CarbonInterface $fecha): bool
    {
        return in_array($fecha->dayOfWeekIso, $this->diasHabiles, true)
            && !$this->esFestivo($fecha);
    }

    /* ---------------------------------------------------------------
     | Aritmética
     * --------------------------------------------------------------- */

    /**
     * Instante hábil más cercano hacia adelante: si cae fuera de jornada,
     * se mueve al inicio de la siguiente.
     */
    public function siguienteHabil(CarbonInterface $desde): CarbonImmutable
    {
        $cursor = CarbonImmutable::instance($desde);

        for ($i = 0; $i < self::MAX_DIAS; $i++) {
            if (!$this->esDiaHabil($cursor)) {
                $cursor = $cursor->addDay()->setTime($this->horaInicio, 0);
                continue;
            }

            if ($cursor->hour < $this->horaInicio) {
                return $cursor->setTime($this->horaInicio, 0);
            }

            if ($cursor->hour >= $this->horaFin) {
                $cursor = $cursor->addDay()->setTime($this->horaInicio, 0);
                continue;
            }

            return $cursor;
        }

        return $cursor;
    }

    public function sumarMinutos(CarbonInterface $desde, int $minutos): CarbonImmutable
    {
        $cursor = $this->siguienteHabil($desde);
        $restantes = max(0, $minutos);

        for ($i = 0; $i < self::MAX_DIAS && $restantes > 0; $i++) {
            $finJornada = $cursor->setTime($this->horaFin, 0);
            $disponibles = (int) $cursor->diffInMinutes($finJornada, false);

            if ($disponibles >= $restantes) {
                return $cursor->addMinutes($restantes);
            }

            $restantes -= max(0, $disponibles);
            $cursor = $this->siguienteHabil($cursor->addDay()->setTime($this->horaInicio, 0));
        }

        return $cursor;
    }

    public function sumarHoras(CarbonInterface $desde, float $horas): CarbonImmutable
    {
        return $this->sumarMinutos($desde, (int) round($horas * 60));
    }

    /**
     * Suma días hábiles completos y deja el vencimiento al cierre de la jornada
     * de ese día, que es como la operación entiende "cinco días hábiles".
     */
    public function sumarDias(CarbonInterface $desde, int $dias): CarbonImmutable
    {
        $cursor = $this->siguienteHabil($desde);

        for ($contados = 0; $contados < $dias; $contados++) {
            $cursor = $cursor->addDay();

            while (!$this->esDiaHabil($cursor)) {
                $cursor = $cursor->addDay();
            }
        }

        return $cursor->setTime($this->horaFin, 0);
    }

    /** Minutos hábiles transcurridos entre dos instantes (0 si el orden se invierte). */
    public function minutosEntre(CarbonInterface $desde, CarbonInterface $hasta): int
    {
        $inicio = CarbonImmutable::instance($desde);
        $fin = CarbonImmutable::instance($hasta);

        if ($fin <= $inicio) {
            return 0;
        }

        $cursor = $this->siguienteHabil($inicio);
        $total = 0;

        for ($i = 0; $i < self::MAX_DIAS && $cursor < $fin; $i++) {
            $finJornada = $cursor->setTime($this->horaFin, 0);
            $corte = $fin < $finJornada ? $fin : $finJornada;

            $total += max(0, (int) $cursor->diffInMinutes($corte, false));

            $cursor = $this->siguienteHabil($cursor->addDay()->setTime($this->horaInicio, 0));
        }

        return $total;
    }

    /* ---------------------------------------------------------------
     | Festivos de Colombia
     * --------------------------------------------------------------- */

    /**
     * @return array<string, true> claves 'Y-m-d'
     */
    public static function festivosColombia(int $anio): array
    {
        if (isset(static::$cacheFestivos[$anio])) {
            return static::$cacheFestivos[$anio];
        }

        $fechas = [];

        $agregar = function (CarbonImmutable $fecha) use (&$fechas) {
            $fechas[$fecha->format('Y-m-d')] = true;
        };

        // Fijos: no se corren nunca.
        foreach ([[1, 1], [5, 1], [7, 20], [8, 7], [12, 8], [12, 25]] as [$mes, $dia]) {
            $agregar(CarbonImmutable::create($anio, $mes, $dia, 0, 0, 0));
        }

        // Ley Emiliani: se trasladan al lunes siguiente si no caen en lunes.
        foreach ([[1, 6], [3, 19], [6, 29], [8, 15], [10, 12], [11, 1], [11, 11]] as [$mes, $dia]) {
            $agregar(static::alLunes(CarbonImmutable::create($anio, $mes, $dia, 0, 0, 0)));
        }

        $pascua = static::domingoDePascua($anio);

        // Jueves y Viernes Santo conservan su fecha.
        $agregar($pascua->subDays(3));
        $agregar($pascua->subDays(2));

        // Ascensión, Corpus Christi y Sagrado Corazón sí se corren al lunes.
        foreach ([43, 64, 71] as $offset) {
            $agregar(static::alLunes($pascua->addDays($offset)));
        }

        return static::$cacheFestivos[$anio] = $fechas;
    }

    private static function alLunes(CarbonImmutable $fecha): CarbonImmutable
    {
        return $fecha->dayOfWeekIso === 1
            ? $fecha
            : $fecha->addDays(8 - $fecha->dayOfWeekIso);
    }

    /** Algoritmo de Meeus/Jones/Butcher (calendario gregoriano). */
    private static function domingoDePascua(int $anio): CarbonImmutable
    {
        $a = $anio % 19;
        $b = intdiv($anio, 100);
        $c = $anio % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);

        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($anio, $mes, $dia, 0, 0, 0);
    }
}
