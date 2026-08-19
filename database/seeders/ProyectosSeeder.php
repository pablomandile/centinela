<?php

namespace Database\Seeders;

use App\Models\Proyecto;
use Illuminate\Database\Seeder;

/**
 * Los proyectos que existen hoy.
 *
 * Corre también en producción, así que es **idempotente y no destructivo**:
 * `firstOrCreate` por slug. A propósito no es `updateOrCreate` —eso pisaría los
 * cambios hechos desde la app, como una bandera corregida a mano o una palabra
 * clave— y este seeder se vuelve a correr cada vez que aparece un proyecto nuevo.
 *
 * Las tres banderas técnicas (usa_inertia, es_pwa, tiene_bundle) no son conjeturas:
 * salieron de correr `centinela:detectar-perfil` contra los sitios el 2026-08-19.
 * Dos sorpresas de esa corrida quedaron acá: hoytrasnoche es PHP sin build **y**
 * tiene manifest válido, y el manifest de localia era un falso positivo (el hosting
 * contesta la home con 200 para cualquier ruta).
 *
 * Centinela **no se incluye a sí mismo**: no puede avisar que se cayó estando
 * caído. Para eso está la ruta /salud y un monitor externo.
 */
class ProyectosSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->proyectos() as $orden => $datos) {
            Proyecto::firstOrCreate(
                ['slug' => $datos['slug']],
                [...$datos, 'orden' => $orden],
            );
        }
    }

    /**
     * Estado verificado por HTTP el 2026-08-19.
     *
     * Los cuatro inactivos del final no resuelven hoy: o no están publicados o
     * viven en un dominio que todavía no conocemos. Se cargan igual para tenerlos
     * a la vista, en gris y sin generar chequeos ni avisos.
     *
     * @return list<array<string, mixed>>
     */
    private function proyectos(): array
    {
        return [
            [
                'nombre' => 'Huella',
                'slug' => 'huella',
                'url' => 'https://huella.pablomandile.com.ar',
                'repo_url' => 'https://github.com/pablomandile/huella',
                'usa_inertia' => true,
                'es_pwa' => true,
                'tiene_bundle' => true,
                'notas' => 'Historial de salud de mascotas. Es el proyecto de referencia del stack.',
            ],
            [
                'nombre' => 'Mantreando',
                'slug' => 'mantreando',
                'url' => 'https://mantreando.pablomandile.com.ar',
                'repo_url' => 'https://github.com/pablomandile/mantreando',
                'usa_inertia' => true,
                'es_pwa' => true,
                'tiene_bundle' => true,
                'notas' => 'Tiene además build de Android con Capacitor.',
            ],
            [
                'nombre' => 'Movieboxd',
                'slug' => 'movieboxd',
                'url' => 'https://movieboxd.pablomandile.com.ar',
                'repo_url' => 'https://github.com/pablomandile/movieboxd',
                'usa_inertia' => true,
                'es_pwa' => true,
                'tiene_bundle' => true,
                'notas' => 'Consume la API de TMDB desde el server. El docroot del subdominio quedó en pablomandile/public/movieboxd y se arregló con un symlink.',
            ],
            [
                'nombre' => 'Secretos',
                'slug' => 'secretos',
                'url' => 'https://secretos.pablomandile.com.ar',
                'repo_url' => 'https://github.com/pablomandile/secretos',
                'usa_inertia' => false,
                'es_pwa' => true,
                'tiene_bundle' => true,
                'notas' => 'SPA con vue-router y PrimeVue, sin Inertia. Usa WebCrypto: necesita https.',
            ],
            [
                'nombre' => 'Escríbelo',
                'slug' => 'escribelo',
                'url' => 'https://escribelo.pablomandile.com.ar',
                'repo_url' => 'https://github.com/pablomandile/escribelo',
                'usa_inertia' => true,
                'es_pwa' => false,
                'tiene_bundle' => true,
            ],
            [
                'nombre' => 'Bioinfo',
                'slug' => 'bioinfo',
                'url' => 'https://bioinfo.pablomandile.com.ar',
                'repo_url' => 'https://github.com/pablomandile/bioinfo',
                'usa_inertia' => true,
                'es_pwa' => false,
                'tiene_bundle' => true,
                'notas' => 'La raíz redirige a /login.',
            ],
            [
                'nombre' => 'Mi Billetera',
                'slug' => 'mibilletera',
                'url' => 'https://mibilletera.pablomandile.com.ar',
                'repo_url' => 'https://github.com/pablomandile/mibilletera',
                'usa_inertia' => true,
                'es_pwa' => true,
                'tiene_bundle' => true,
                'notas' => 'La raíz redirige a /login.',
            ],
            [
                'nombre' => 'Hoy Trasnoche',
                'slug' => 'hoytrasnoche',
                'url' => 'https://hoytrasnoche.pablomandile.com.ar',
                'usa_inertia' => false,
                'es_pwa' => true,
                'tiene_bundle' => false,
                'notas' => 'PHP sin framework, con scripts de Python al costado. Sin repo en GitHub.',
            ],
            [
                'nombre' => 'Pablo Mandile',
                'slug' => 'pablomandile',
                'url' => 'https://pablomandile.com.ar',
                'repo_url' => 'https://github.com/pablomandile/pablomandile',
                'usa_inertia' => true,
                'es_pwa' => false,
                'tiene_bundle' => true,
                'notas' => 'El sitio principal. Su public/ hospeda los symlinks de algunos subdominios.',
            ],
            [
                'nombre' => 'AgendaFlex',
                'slug' => 'agendaflex',
                'url' => 'https://agendaflex.com.ar',
                'repo_url' => 'https://github.com/pablomandile/agendaflex',
                'usa_inertia' => true,
                'es_pwa' => false,
                'tiene_bundle' => true,
                'notas' => 'Dominio propio, no subdominio. Se despliega por git y su public/.htaccess está modificado en el server (AddHandler php84).',
            ],
            [
                'nombre' => 'Localia',
                'slug' => 'localia',
                'url' => 'https://localia.com.ar',
                'usa_inertia' => false,
                'es_pwa' => false,
                'tiene_bundle' => false,
                'notas' => 'Dominio propio. La documentación son dos PDF.',
            ],
            [
                'nombre' => 'Primera Web 1998',
                'slug' => 'primeraweb1998',
                'url' => 'https://primeraweb1998.pablomandile.com.ar',
                'usa_inertia' => false,
                'es_pwa' => false,
                'tiene_bundle' => false,
                'notas' => 'HTML y applets de Java de 1998. No tiene build ni service worker.',
            ],

            // ---- Todavía no publicados (o en un dominio que no conocemos) ----

            [
                'nombre' => 'Docbrainer',
                'slug' => 'docbrainer',
                'url' => 'https://docbrainer.pablomandile.com.ar',
                'usa_inertia' => true,
                'es_pwa' => false,
                'tiene_bundle' => true,
                'activo' => false,
                'notas' => 'No resuelve al 2026-08-19.',
            ],
            [
                'nombre' => 'Meditar en Zona Norte',
                'slug' => 'meditarenzn',
                'url' => 'https://meditarenzn.pablomandile.com.ar',
                'repo_url' => 'https://github.com/pablomandile/meditarenzonanorte',
                'usa_inertia' => true,
                'es_pwa' => false,
                'tiene_bundle' => true,
                'activo' => false,
                'notas' => 'No resuelve al 2026-08-19: revisar en qué dominio quedó.',
            ],
            [
                'nombre' => 'Milarepa',
                'slug' => 'milarepa',
                'url' => 'https://milarepa.pablomandile.com.ar',
                'repo_url' => 'https://github.com/pablomandile/milarepa',
                'usa_inertia' => true,
                'es_pwa' => false,
                'tiene_bundle' => true,
                'activo' => false,
                'notas' => 'No resuelve al 2026-08-19. Es el proyecto con más documentación (12 archivos .md).',
            ],
            [
                'nombre' => 'Dharmify',
                'slug' => 'dharmify',
                'url' => 'https://dharmify.pablomandile.com.ar',
                'usa_inertia' => false,
                'es_pwa' => false,
                'tiene_bundle' => false,
                'activo' => false,
                'notas' => 'Carpeta local vacía: todavía no existe.',
            ],
        ];
    }
}
