<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Quién puede entrar
    |--------------------------------------------------------------------------
    |
    | Centinela es una herramienta personal: no tiene registro. La allowlist es
    | la única puerta. Un email que no esté acá no entra y **no crea usuario**,
    | ni siquiera con una cuenta de Google válida.
    |
    | Se escribe en el .env separada por comas:
    | CENTINELA_EMAILS=uno@ejemplo.com,otro@ejemplo.com
    |
    */

    'emails_habilitados' => array_values(array_filter(array_map(
        fn (string $email): string => mb_strtolower(trim($email)),
        explode(',', (string) env('CENTINELA_EMAILS', '')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Avisos
    |--------------------------------------------------------------------------
    |
    | A dónde van los mails de caída y de recuperación. Si queda vacío, los
    | chequeos corren igual y no se manda nada: el tablero sigue mostrando los
    | incidentes.
    |
    */

    'avisos_a' => env('CENTINELA_AVISOS_A'),

    /*
    |--------------------------------------------------------------------------
    | Umbrales de los chequeos
    |--------------------------------------------------------------------------
    |
    | Están acá y no repartidos por las sondas para poder ajustarlos sin tocar
    | código y para que los tests los puedan pisar.
    |
    */

    'umbrales' => [
        // Segundos de espera antes de dar por caído un sitio.
        'timeout' => 15,

        // Redirects a seguir. La raíz de varios proyectos contesta 302 a /login:
        // no seguirlos sería marcar como caído un sitio sano.
        'redirects' => 5,

        // Latencia (ms) que pasa de "ok" a "advertencia".
        'latencia_advertencia' => 3000,

        // Días de vida del certificado TLS que disparan advertencia y falla.
        'certificado_advertencia' => 21,
        'certificado_falla' => 7,

        // Fallos seguidos antes de abrir un incidente. Con 1 avisaría por
        // cualquier hipo de red.
        'fallos_para_incidente' => 2,

        // Días de historial de chequeos que se conservan.
        'retencion_dias' => 90,
    ],

];
