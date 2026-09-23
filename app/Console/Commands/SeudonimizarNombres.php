<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Reasigna los nombres falsos del histórico.
 *
 * El histórico de este proyecto sale de una operación real, con permiso para
 * usarlo, y los nombres de los abonados se sustituyeron por unos inventados
 * antes de subir nada. El primer intento combinó una lista corta de nombres con
 * una lista corta de apellidos: 144 combinaciones para 4.114 personas, o sea
 * cada nombre repetido veintinueve veces. Como la lista de clientes se ordena
 * por nombre, las copias caían juntas y la primera pantalla parecía un solo
 * cliente clonado.
 *
 * Esto lo arregla y deja el método a la vista, que es lo que faltaba: quien
 * clone el repositorio puede volver a generar los nombres y ver cómo se hizo.
 *
 * Dos propiedades que importan:
 *
 *  - **Determinista.** El nombre sale del código de abonado, así que correrlo
 *    dos veces da lo mismo y el CSV no cambia sin motivo entre commits.
 *  - **Sin repeticiones.** El espacio es de cientos de miles de combinaciones
 *    y las colisiones se resuelven avanzando, no aceptando el choque.
 *
 * Lo que NO hace, a propósito: tocar la cédula, el teléfono o el código de
 * abonado. Esos ya son sintéticos y son los que amarran cada caso con su
 * cliente; reasignarlos rompería el histórico entero.
 */
class SeudonimizarNombres extends Command
{
    protected $signature = 'datos:seudonimizar-nombres
                            {--archivo=database/data/historico_clientes.csv : CSV de clientes}
                            {--dry-run : Muestra una muestra sin escribir el archivo}';

    protected $description = 'Reasigna nombres falsos únicos a los abonados del histórico';

    /** Nombres de pila, mezclando hombres y mujeres. */
    private const NOMBRES = [
        'Adriana', 'Alberto', 'Alejandra', 'Alonso', 'Amparo', 'Andrés', 'Ángela', 'Aníbal',
        'Beatriz', 'Bernardo', 'Camila', 'Carlos', 'Carmenza', 'César', 'Clara', 'Cristian',
        'Daniela', 'Darío', 'Diana', 'Diego', 'Dolores', 'Duván', 'Edilma', 'Eduardo',
        'Elena', 'Elkin', 'Emilse', 'Ernesto', 'Esteban', 'Estela', 'Fabián', 'Fanny',
        'Fernando', 'Flor', 'Francisco', 'Gabriela', 'Germán', 'Gloria', 'Gustavo', 'Héctor',
        'Helena', 'Hernán', 'Ignacio', 'Inés', 'Isabel', 'Iván', 'Jaime', 'Javier',
        'Jimena', 'Jorge', 'Josefina', 'Juliana', 'Leonardo', 'Leticia', 'Lorena', 'Lucía',
        'Luis', 'Magnolia', 'Manuel', 'Marcela', 'Margarita', 'Mariana', 'Mario', 'Martha',
        'Mauricio', 'Mónica', 'Natalia', 'Nelson', 'Norberto', 'Nubia', 'Octavio', 'Olga',
        'Óscar', 'Patricia', 'Paula', 'Pedro', 'Rafael', 'Ramiro', 'Raquel', 'Ricardo',
        'Rocío', 'Rodrigo', 'Rosalba', 'Rubén', 'Salomé', 'Samuel', 'Sandra', 'Sebastián',
        'Silvia', 'Sofía', 'Stella', 'Teresa', 'Tomás', 'Valentina', 'Vicente', 'Yolanda',
    ];

    /** Apellidos corrientes en el Eje Cafetero, que es de donde salen los datos. */
    private const APELLIDOS = [
        'Acevedo', 'Agudelo', 'Alzate', 'Arango', 'Aristizábal', 'Arredondo', 'Bedoya', 'Betancur',
        'Buitrago', 'Cardona', 'Carmona', 'Castaño', 'Ceballos', 'Cifuentes', 'Correa', 'Cortés',
        'Duque', 'Echeverri', 'Escobar', 'Espinal', 'Estrada', 'Franco', 'Gallego', 'Galvis',
        'García', 'Giraldo', 'Gómez', 'González', 'Grajales', 'Guarín', 'Gutiérrez', 'Henao',
        'Herrera', 'Higuita', 'Hincapié', 'Hoyos', 'Ibarra', 'Jaramillo', 'Loaiza', 'London',
        'López', 'Marín', 'Mejía', 'Mesa', 'Montoya', 'Morales', 'Moreno', 'Muñoz',
        'Naranjo', 'Noreña', 'Ocampo', 'Ochoa', 'Orozco', 'Ortiz', 'Ospina', 'Osorio',
        'Palacio', 'Pareja', 'Patiño', 'Peláez', 'Pérez', 'Posada', 'Quintero', 'Quiceno',
        'Ramírez', 'Restrepo', 'Rincón', 'Ríos', 'Rivera', 'Rodas', 'Rojas', 'Salazar',
        'Sánchez', 'Sepúlveda', 'Serna', 'Soto', 'Tabares', 'Toro', 'Trujillo', 'Uribe',
        'Valencia', 'Vanegas', 'Vargas', 'Vásquez', 'Velásquez', 'Villa', 'Wilches', 'Zapata',
    ];

    public function handle(): int
    {
        $ruta = $this->resolver($this->option('archivo'));

        if (! is_readable($ruta)) {
            $this->error("No encuentro el archivo: {$ruta}");

            return self::FAILURE;
        }

        [$encabezados, $filas] = $this->leer($ruta);

        if (! in_array('nombre', $encabezados, true) || ! in_array('codigo_abonado', $encabezados, true)) {
            $this->error("El CSV necesita las columnas 'codigo_abonado' y 'nombre'.");

            return self::FAILURE;
        }

        $usados = [];

        foreach ($filas as $i => $fila) {
            $filas[$i]['nombre'] = $this->nombrePara($fila['codigo_abonado'], $usados);
        }

        $this->muestra($filas);

        $combinaciones = count(self::NOMBRES) * count(self::APELLIDOS) * (count(self::APELLIDOS) - 1);

        $this->line(sprintf(
            '  %s abonados, %s nombres distintos, de %s combinaciones posibles.',
            number_format(count($filas)),
            number_format(count($usados)),
            number_format($combinaciones),
        ));

        if ($this->option('dry-run')) {
            $this->info('[simulación] El archivo no se tocó.');

            return self::SUCCESS;
        }

        $this->escribir($ruta, $encabezados, $filas);

        $this->info('Nombres reasignados. Falta llevarlos a la base:');
        $this->line('  php artisan soportes:importar-historico --actualizar');

        return self::SUCCESS;
    }

    /**
     * El nombre sale del código de abonado, no del azar.
     *
     * La semilla se reparte entre las tres posiciones dividiendo por el tamaño
     * de la lista anterior, para que dos códigos parecidos no caigan en nombres
     * parecidos. Si el nombre completo ya salió, se avanza la semilla y se
     * vuelve a probar: con cientos de miles de combinaciones eso ocurre poco, y
     * cuando ocurre se resuelve en una o dos vueltas.
     *
     * @param  array<string, true>  $usados
     */
    private function nombrePara(string $codigo, array &$usados): string
    {
        $nombres = count(self::NOMBRES);
        $apellidos = count(self::APELLIDOS);

        $semilla = crc32($codigo);

        for ($intento = 0; $intento < 1000; $intento++) {
            $n = self::NOMBRES[$semilla % $nombres];
            $primero = self::APELLIDOS[intdiv($semilla, $nombres) % $apellidos];
            $segundo = self::APELLIDOS[intdiv($semilla, $nombres * $apellidos) % $apellidos];

            // Nadie se apellida igual dos veces; se corre uno al siguiente.
            if ($primero === $segundo) {
                $segundo = self::APELLIDOS[(intdiv($semilla, $nombres * $apellidos) + 1) % $apellidos];
            }

            $completo = "{$n} {$primero} {$segundo}";

            if (! isset($usados[$completo])) {
                $usados[$completo] = true;

                return $completo;
            }

            $semilla++;
        }

        // Inalcanzable con las listas actuales; si alguien las recorta, que se note.
        throw new \RuntimeException("Se agotaron las combinaciones de nombres en {$codigo}.");
    }

    /** @return array{0: array<int, string>, 1: array<int, array<string, string>>} */
    private function leer(string $ruta): array
    {
        $manejador = fopen($ruta, 'r');

        // El BOM que Excel deja al inicio se pega al primer encabezado.
        $encabezados = array_map(
            fn(string $c) => trim(ltrim($c, "\xEF\xBB\xBF")),
            fgetcsv($manejador, escape: ''),
        );

        $filas = [];

        while (($fila = fgetcsv($manejador, escape: '')) !== false) {
            if ($fila === [null] || $fila === []) {
                continue;
            }

            $filas[] = array_combine($encabezados, array_pad($fila, count($encabezados), ''));
        }

        fclose($manejador);

        return [$encabezados, $filas];
    }

    /**
     * @param  array<int, string>  $encabezados
     * @param  array<int, array<string, string>>  $filas
     */
    private function escribir(string $ruta, array $encabezados, array $filas): void
    {
        $manejador = fopen($ruta, 'w');

        // Coma y sin BOM: este CSV lo lee el importador, no Excel.
        fputcsv($manejador, $encabezados, escape: '');

        foreach ($filas as $fila) {
            fputcsv($manejador, array_values($fila), escape: '');
        }

        fclose($manejador);
    }

    /** @param array<int, array<string, string>> $filas */
    private function muestra(array $filas): void
    {
        $this->table(
            ['Abonado', 'Nombre'],
            collect($filas)->take(5)->map(fn($f) => [$f['codigo_abonado'], $f['nombre']])->all(),
        );
    }

    private function resolver(string $ruta): string
    {
        $absoluta = str_starts_with($ruta, '/')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $ruta) === 1;

        return $absoluta ? $ruta : base_path($ruta);
    }
}
