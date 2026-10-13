<?php

use App\Models\Soporte;
use Illuminate\Support\Facades\Blade;

/*
 * El semáforo nunca depende solo del color: cada estado lleva su forma, su
 * palabra y su tiempo. Estas pruebas lo revisan sobre el HTML que se dibuja.
 */

function casoConSla(?string $vence, bool $pausado = false): Soporte
{
    $caso = soporteDePrueba();
    $caso->forceFill([
        'sla_vence_at' => $vence,
        'sla_pausado_at' => $pausado ? now() : null,
        'sla_minutos_restantes' => $pausado ? 120 : null,
        'sla_presupuesto_minutos' => 240,
    ])->saveQuietly();

    return $caso->refresh();
}

function semaforo(Soporte $caso): string
{
    return Blade::render('<x-sla :soporte="$caso" />', ['caso' => $caso]);
}

it('cada estado del semáforo tiene su palabra y su forma propia', function () {
    $this->travelTo(now()->next('Tuesday')->setTime(10, 0));

    $html = [
        'En plazo' => semaforo(casoConSla(now()->addHours(4))),
        'Atención' => semaforo(casoConSla(now()->addMinutes(80))),
        'En riesgo' => semaforo(casoConSla(now()->addMinutes(20))),
        'Vencido' => semaforo(casoConSla(now()->subMinutes(30))),
        'En pausa' => semaforo(casoConSla(now()->addHours(2), pausado: true)),
    ];

    $formas = [];

    foreach ($html as $palabra => $fragmento) {
        expect($fragmento)->toContain($palabra);
        preg_match('/<svg[^>]*>(.*?)<\/svg>/s', $fragmento, $svg);
        $formas[$palabra] = preg_replace('/\s+/', ' ', $svg[1] ?? '');
    }

    expect(array_unique($formas))->toHaveCount(5);
});

it('vencido es el único estado con relleno sólido', function () {
    $this->travelTo(now()->next('Tuesday')->setTime(10, 0));

    expect(semaforo(casoConSla(now()->subMinutes(30))))->toContain('bg-sla-vencido text-white')
        ->and(semaforo(casoConSla(now()->addMinutes(20))))->not->toContain('bg-sla-vencido text-white');
});

it('muestra el tiempo restante junto a la palabra', function () {
    $this->travelTo(now()->next('Tuesday')->setTime(10, 0));

    expect(semaforo(casoConSla(now()->addMinutes(20))))->toMatch('/En riesgo\s*<span[^>]*>· 20 min<\/span>/');
});

it('un caso cerrado no muestra reloj', function () {
    $caso = casoConSla(now()->addHours(2));
    $caso->forceFill(['estado' => Soporte::ESTADO_SOLUCIONADO])->saveQuietly();

    $html = semaforo($caso->refresh());

    expect($html)->toContain('Cerrado')->not->toContain('<svg');
});

it('el chip de estado dice el estado y no usa relleno de alarma', function () {
    $html = Blade::render('<x-estado estado="escalado_n3" />');

    expect($html)->toContain('En ingeniería de redes')->not->toContain('bg-sla-vencido');
});

it('el botón con href se dibuja como enlace y sin href como botón', function () {
    expect(Blade::render('<x-boton href="/x" variante="primario">Ir</x-boton>'))->toContain('<a href="/x"')
        ->and(Blade::render('<x-boton>Ok</x-boton>'))->toContain('<button type="button"');
});
