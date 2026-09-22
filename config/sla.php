<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Jornada laboral
    |--------------------------------------------------------------------------
    | El reloj de SLA solo corre dentro de estas horas y estos días.
    | 'dias' usa la numeración ISO: 1 = lunes ... 7 = domingo.
    */

    'jornada' => [
        'hora_inicio' => 7,
        'hora_fin' => 18,
        'dias' => [1, 2, 3, 4, 5],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tiempos por criticidad
    |--------------------------------------------------------------------------
    | 'inmediata' se mide en horas hábiles; 'normal' en días hábiles.
    | Criterio de la operación: si el servicio está caído (sin internet,
    | sin TV o sin ambos) se atiende de inmediato; el resto, 5 días hábiles.
    */

    'tiempos' => [
        'inmediata' => [
            'unidad' => 'horas',
            'asignacion' => 0.5,   // 30 min hábiles para que alguien lo tome
            'resolucion' => 4,
        ],
        'normal' => [
            'unidad' => 'dias',
            'asignacion' => 1,
            'resolucion' => 5,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Umbrales del semáforo
    |--------------------------------------------------------------------------
    | Porcentaje del SLA consumido a partir del cual el caso cambia de color.
    */

    'semaforo' => [
        'atencion' => 60,
        'riesgo' => 85,
    ],

    /*
    |--------------------------------------------------------------------------
    | Alertas por correo
    |--------------------------------------------------------------------------
    | El semáforo de la bandeja solo lo ve quien está mirando la pantalla. Un
    | caso inmediato tiene cuatro horas hábiles: si nadie abre la bandeja en
    | esas cuatro horas, el color no sirvió de nada. El comando
    | `soportes:alertar-sla` avisa al técnico asignado cuando su caso entra en
    | riesgo, y a los roles de aquí abajo cuando algo ya se venció o cuando
    | nadie lo ha tomado.
    |
    | El umbral de riesgo es el mismo del semáforo, arriba: no tiene sentido
    | que la pantalla diga una cosa y el correo otra.
    */

    'alertas' => [
        'activas' => true,
        'copia_gestion' => ['admin', 'gerente'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sin contacto
    |--------------------------------------------------------------------------
    | Intentos antes de cerrar el caso como "sin contacto definitivo" y
    | horas hábiles que se esperan entre un intento y el siguiente.
    */

    'sin_contacto' => [
        'max_intentos' => 3,
        'horas_entre_intentos' => 4,
    ],

    /*
    |--------------------------------------------------------------------------
    | Abonados recurrentes
    |--------------------------------------------------------------------------
    | La operación los llamaba "reincidentes": clientes que vuelven a reportar
    | al poco tiempo. Dos reportes en dos meses ya los ponía en la lista; tres
    | o más en un año era motivo para revisar la instalación en vez de seguir
    | atendiendo síntomas.
    */

    'recurrencia' => [
        'ventana_dias' => 60,
        'minimo_en_ventana' => 2,
        'ventana_larga_meses' => 12,
        'minimo_en_ventana_larga' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Festivos adicionales
    |--------------------------------------------------------------------------
    | Los 18 festivos de Colombia se calculan solos (Ley Emiliani y los
    | móviles de Semana Santa). Aquí van los cierres propios de la empresa,
    | en formato 'YYYY-MM-DD'.
    */

    'festivos_extra' => [],

];
