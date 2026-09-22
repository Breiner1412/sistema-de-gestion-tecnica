<?php

namespace Database\Seeders;

use App\Models\Tecnico;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Equipo de la operación con los roles del flujo real:
 * primer nivel -> técnico de soporte (N2) -> ingeniería de redes (N3) o campo.
 *
 * Los NOMBRES son sintéticos. Lo que se conserva del caso real es el tamaño y
 * la forma del equipo (21 en nivel 2, 28 en primer nivel), que es conocimiento
 * del dominio; los nombres de personas reales no van en un proyecto público.
 *
 * Contraseña inicial para todos: "cambiar123".
 */
class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        $clave = Hash::make('cambiar123');

        $this->crear('Administrador SGT', 'admin@sgt.local', User::ROL_ADMIN, $clave);
        $this->crear('Gerencia Técnica', 'gerencia@sgt.local', User::ROL_GERENTE, $clave);
        $this->crear('Breiner Rodriguez', 'rodriguezbreiner125@gmail.com', User::ROL_ADMIN, $clave);

        // Nivel 2: los que el Excel llamaba "ingenieros" son técnicos de soporte.
        $tecnicosSoporte = [
            ['Andrés Arango Naranjo', 'andres.arango@sgt.local'],
            ['Valentina Hoyos Zapata', 'valentina.hoyos@sgt.local'],
            ['Camilo Ocampo Londoño', 'camilo.ocampo@sgt.local'],
            ['Catalina Valdés Bedoya', 'catalina.valdes@sgt.local'],
            ['Julián Delgado Hoyos', 'julian.delgado@sgt.local'],
            ['Lucía Londoño Salazar', 'lucia.londono@sgt.local'],
            ['Sebastián Toro Estrada', 'sebastian.toro@sgt.local'],
            ['Mariana Espinosa Rendón', 'mariana.espinosa@sgt.local'],
            ['Mateo Gallego Betancur', 'mateo.gallego@sgt.local'],
            ['Daniela Naranjo Montoya', 'daniela.naranjo@sgt.local'],
            ['Nicolás Urrego Yepes', 'nicolas.urrego@sgt.local'],
            ['Paula Castaño Jiménez', 'paula.castano@sgt.local'],
            ['Santiago Jiménez Zuluaga', 'santiago.jimenez@sgt.local'],
            ['Carolina Serna Gallego', 'carolina.serna@sgt.local'],
            ['Felipe Duque Restrepo', 'felipe.duque@sgt.local'],
            ['Isabela Ferrer Delgado', 'isabela.ferrer@sgt.local'],
            ['Esteban Montoya Palacio', 'esteban.montoya@sgt.local'],
            ['Natalia Tabares Arango', 'natalia.tabares@sgt.local'],
            ['Daniel Bermúdez Lozano', 'daniel.bermudez@sgt.local'],
            ['Adriana Ibarra Wilches', 'adriana.ibarra@sgt.local'],
            ['Ricardo Rendón Ibarra', 'ricardo.rendon@sgt.local'],
        ];

        foreach ($tecnicosSoporte as [$nombre, $email]) {
            $this->crear($nombre, $email, User::ROL_TECNICO_SOPORTE, $clave);
        }

        // Primer nivel: caja, call center y WhatsApp.
        $primerNivel = [
            ['Andrés Duarte Estrada', 'andres.duarte@sgt.local'],
            ['Catalina Idárraga Toro', 'catalina.idarraga@sgt.local'],
            ['Lucía Naranjo Ferrer', 'lucia.naranjo@sgt.local'],
            ['Sebastián Salazar Salazar', 'sebastian.salazar@sgt.local'],
            ['Daniela Yepes Guzmán', 'daniela.yepes@sgt.local'],
            ['Paula Delgado Vargas', 'paula.delgado@sgt.local'],
            ['Santiago Ibarra Hoyos', 'santiago.ibarra@sgt.local'],
            ['Isabela Osorio Urrego', 'isabela.osorio@sgt.local'],
            ['Natalia Uribe Ibarra', 'natalia.uribe@sgt.local'],
            ['Daniel Duque Bedoya', 'daniel.duque@sgt.local'],
            ['Ximena Duarte Jaramillo', 'ximena.duarte@sgt.local'],
            ['Verónica Idárraga Wilches', 'veronica.idarraga@sgt.local'],
            ['Hernán Naranjo Londoño', 'hernan.naranjo@sgt.local'],
            ['Tatiana Salazar Duque', 'tatiana.salazar@sgt.local'],
            ['Alejandra Yepes Lozano', 'alejandra.yepes@sgt.local'],
            ['Gustavo Delgado Zapata', 'gustavo.delgado@sgt.local'],
            ['Manuela Ibarra Nieto', 'manuela.ibarra@sgt.local'],
            ['Gabriela Osorio Arango', 'gabriela.osorio@sgt.local'],
            ['Fabián Uribe Naranjo', 'fabian.uribe@sgt.local'],
            ['Diana Duque Bermúdez', 'diana.duque@sgt.local'],
            ['Claudia Duarte Palacio', 'claudia.duarte@sgt.local'],
            ['Andrés Idárraga Cardales', 'andres.idarraga@sgt.local'],
            ['Marcela Naranjo Pineda', 'marcela.naranjo@sgt.local'],
            ['Elena Salazar Delgado', 'elena.salazar@sgt.local'],
            ['Sebastián Yepes Serna', 'sebastian.yepes@sgt.local'],
            ['Patricia Delgado Escobar', 'patricia.delgado@sgt.local'],
            ['Silvia Ibarra Restrepo', 'silvia.ibarra@sgt.local'],
            ['Santiago Osorio Franco', 'santiago.osorio@sgt.local'],
        ];

        foreach ($primerNivel as [$nombre, $email]) {
            $this->crear($nombre, $email, User::ROL_CALL_CENTER, $clave);
        }

        foreach ([1, 2] as $i) {
            $this->crear("Ingeniero de redes {$i}", "redes{$i}@sgt.local", User::ROL_INGENIERO_REDES, $clave);
        }

        foreach ([1, 2, 3] as $i) {
            $user = $this->crear("Técnico de campo {$i}", "campo{$i}@sgt.local", User::ROL_TECNICO_CAMPO, $clave);

            Tecnico::firstOrCreate(['user_id' => $user->id], [
                'nombre' => $user->name,
                'estado' => 'activo',
            ]);
        }
    }

    private function crear(string $nombre, string $email, string $rol, string $clave): User
    {
        return User::firstOrCreate(['email' => $email], [
            'name' => $nombre,
            'password' => $clave,
            'rol' => $rol,
            'estado' => 'activo',
            'email_verified_at' => now(),
        ]);
    }
}
