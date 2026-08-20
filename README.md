# Centinela

Herramienta personal con dos módulos:

1. **Salud.** Vigila los sitios ya publicados y avisa por mail cuando uno se cae o se
   recupera. Además de si contestan, audita las cosas que en esta cuenta de hosting ya
   costaron tiempo: que el JSON de Inertia no se pueda guardar, que el service worker y
   el manifest no queden cacheados por el CDN, que el bundle que pide la página exista
   y cuánto le queda al certificado.
2. **Documentación.** Agrupa por proyecto los `.md` y `.pdf` que estaban sueltos en las
   raíces de `c:\laragon\www`, con visor, buscador y exportación a PDF.

Instalable como app (PWA) en escritorio y celular. Se entra con Google.

En producción: **https://centinela.pablomandile.com.ar**

## Con qué está hecho

| Capa | Tecnología |
|---|---|
| Backend | **Laravel 13** sobre **PHP 8.4** |
| Base | **MySQL 8** (los tests corren en sqlite en memoria) |
| Frontend | **Inertia 3** + **Vue 3** con **TypeScript**, compilado con **Vite 8** |
| Estilos | **Tailwind 4** y componentes **shadcn-vue** (sobre reka-ui) |
| Rutas tipadas | **Wayfinder** (no Ziggy) |
| Autenticación | **Fortify** recortado al login + **Socialite** para Google |
| Gráficos | **Chart.js** con vue-chartjs |
| Fechas | **Carbon** en el backend (todo en UTC), **dayjs** en el navegador |
| Markdown | **league/commonmark** vía `Str::markdown()`, que ya viene con Laravel |
| PDF | **dompdf** (`barryvdh/laravel-dompdf`) |
| Tests | **Pest 4** sobre PHPUnit 12 |
| Análisis estático | **PHPStan/Larastan nivel 7**, **Pint**, **ESLint**, **Prettier**, **vue-tsc** |
| PWA | manifest, service worker y botón de instalar propios, sin librería |
| Hosting | Hostinger compartido (Apache/LiteSpeed), deploy por SSH |

Es el mismo stack que **huella**, que es el proyecto de referencia de esta cuenta: así
lo que se aprende en uno sirve en el otro.

Dos decisiones que explican varias cosas del código:

- **Sin cola.** En hosting compartido un worker se cae en silencio, así que los mails
  los manda el propio comando del scheduler. Un solo cron por minuto alcanza para todo.
- **Sin Node en el servidor.** Se compila en la máquina de desarrollo y se copia
  `public/build`.

## Arrancar

```bash
composer setup     # install + .env + key + migrate + npm install + build
php artisan db:seed --class=ProyectosSeeder
composer dev       # server + vite + logs
```

Hace falta MySQL con una base `centinela` y PHP 8.4.

Para entrar por Google en local hay que usar `http://localhost:8000`: Google no acepta
`http://` en dominios que no sean localhost, así que `centinela.test` no sirve para eso.
Ver [docs/google-oauth.md](docs/google-oauth.md).

## Los comandos

```bash
php artisan centinela:chequear                     # lo que le toca a cada proyecto
php artisan centinela:chequear --forzar            # todo, ya
php artisan centinela:chequear --proyecto=huella   # uno solo, aunque esté inactivo
php artisan centinela:detectar-perfil --aplicar    # le pregunta a cada sitio qué usa
php artisan centinela:podar                        # recorta el historial a 90 días
```

En producción alcanza **un** cron por minuto con `schedule:run`; el comando decide qué
le toca a cada proyecto. No hay worker de cola.

## Cómo está armado

- `app/Sondas/` — una clase por chequeo detrás de la interfaz `Sonda`, y
  `RegistroDeSondas` como único lugar que decide cuál aplica a qué proyecto.
- `app/Services/EjecutorDeChequeos.php` — corre las sondas, guarda el resultado y abre
  o cierra incidentes (al segundo fallo seguido, uno por proyecto y tipo).
- `app/Services/DocumentoService.php` / `MarkdownService.php` — subida al disco privado
  y render del markdown con su índice.

Las convenciones y las trampas conocidas están en [CLAUDE.md](CLAUDE.md); el plan
original en [docs/plan.md](docs/plan.md); el deploy en [docs/deploy.md](docs/deploy.md).

## Calidad

```bash
composer ci:check   # eslint + prettier + vue-tsc + pint + phpstan nivel 7 + tests
```
