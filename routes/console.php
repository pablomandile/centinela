<?php

use Illuminate\Support\Facades\Schedule;

/*
 * Un solo cron en hPanel alcanza para todo esto:
 *
 *   /opt/alt/php84/usr/bin/php /home/<CUENTA>/domains/pablomandile.com.ar/centinela/artisan schedule:run
 *
 * (el usuario real de la cuenta está en el skill `deploy-hostinger`, que vive a
 * nivel usuario y no en este repo, que es público).
 *
 * Con ruta absoluta a artisan y **sin `cd ... &&`**: el ejecutor de cron de hPanel
 * no pasa el comando por un shell, así que el `cd` muere y nunca se llega al php.
 * El síntoma es silencioso —el cron figura creado y no corre nunca—, y está
 * documentado en el skill `deploy-hostinger`.
 *
 * No hay worker de cola: los mails los manda el propio comando, que ya corre
 * fuera de una petición web.
 */

/*
 * Cada cinco minutos, no cada minuto: el comando no chequea todo lo que puede sino
 * lo que le toca (`Proyecto::toca()`), así que correrlo más seguido solo agrega
 * consultas. El intervalo real de cada proyecto se cambia desde la app.
 */
Schedule::command('centinela:chequear')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->runInBackground();

Schedule::command('centinela:podar')->weeklyOn(1, '04:10');
