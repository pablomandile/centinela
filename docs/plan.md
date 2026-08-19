# Centinela — plan de trabajo

## Contexto

Hay 17 proyectos en `c:\laragon\www` y **12 URLs publicadas** (verificado por HTTP el 2026-08-19).
Hoy no hay ninguna forma de saber si uno se cayó, si un certificado está por vencer o si un
deploy dejó el bundle roto: se descubre por casualidad al entrar. Y la documentación está
desparramada —40 `.md` en raíces de proyecto, algunos en `docs/`, PDFs sueltos en `localia`—
sin índice ni forma de leerla desde el celular.

Centinela resuelve las dos cosas en una sola app: **tablero de salud** de los sitios en línea
y **biblioteca de documentación** agrupada por proyecto (`.md` y `.pdf`). Instalable como app
en escritorio y celular, con ingreso por Google, desplegada en
`https://centinela.pablomandile.com.ar`.

Hay un segundo objetivo, explícito en el pedido: **que los skills ya escritos se conviertan en
chequeos automáticos**. Cada trampa que costó tiempo (el JSON crudo de Inertia, el `sw.js` viejo
sirviéndose desde el CDN una semana, el manifest con MIME equivocado) hoy se detecta a mano y de
a un proyecto. Centinela las audita a los 12 sitios cada día.

## Decisiones ya tomadas (no volver a discutirlas)

| Tema | Decisión |
|------|----------|
| Documentos | **Subida manual desde la web** (`.md` y `.pdf`). Sin CLI de sync ni pull de GitHub. |
| Login | **Google OAuth** con allowlist de emails en `.env`. Registro cerrado. |
| Chequeos v1 | Básicos **+ auditoría técnica derivada de los skills**. Sin PageSpeed. |
| Avisos | **Email al caer y al recuperarse**. Sin worker de cola. |
| App | Una sola app web responsive, **PWA instalable** en escritorio y celular. Sin Electron ni Capacitor. |

Sobre el login: la respuesta inicial fue «multiusuario», pero el motivo (compartir carpetas de
audios de un evento y una sección pública) se retiró después por ser de otro proyecto. Queda
entonces **un solo usuario, Google + allowlist**. Para no cerrar la puerta: `users` lleva una
columna `rol` desde la primera migración y todo pasa por Policies, así invitar a alguien más
adelante es agregar una fila y un caso en la Policy, no rehacer la autorización.

**Fuera de alcance de v1** (registrado para que no se cuele): audios / OneDrive / rclone,
sección pública sin login, invitados, PageSpeed Insights, sync automático de docs desde el disco
o GitHub, versionado de documentos, y unir PDFs subidos dentro del dossier.

## Stack

El de `huella`, que es el proyecto más nuevo y el que ya tiene resueltas todas las piezas que
Centinela necesita:

Laravel 13 · PHP 8.4 · MySQL 8 · Inertia 3 · Vue 3 + TypeScript · Tailwind 4 · Vite ·
shadcn-vue sobre reka-ui · Wayfinder (no Ziggy) · Pest 4 (PHPUnit 12, **no** subir a Pest 5).

Paquetes nuevos: uno solo, `barryvdh/laravel-dompdf ^3.1` (el mismo que usa huella para el PDF
de historia clínica). `league/commonmark` y `guzzlehttp/guzzle` **ya vienen** en el árbol de
`laravel/framework`: el Markdown va con `Str::markdown()` y los chequeos con el cliente `Http`,
sin sumar dependencias.

Punto de partida: starter kit Vue oficial (trae layouts, sidebar y `components/ui/` armados) con
Fortify recortado — sin registro ni reset de contraseña, sin 2FA, sin passkeys.

## Fase 0 — Revisión de skills (antes de escribir código)

Pedido explícito del usuario y también la parte que más tiempo ahorra. Leer los 6 skills
relevantes **antes** de arrancar, y volcar el resultado en el `CLAUDE.md` del proyecto como
decisiones cerradas. La tabla es el mapa de qué skill resuelve qué, para no re-descubrirlo ni
implementar dos veces el mismo flujo:

| Skill | Qué aporta a Centinela | Cuándo se aplica |
|---|---|---|
| `deploy-hostinger` | Host/puerto/usuario, `/opt/alt/php84/usr/bin/php`, backup + `build_new` + swap, cron de hPanel **sin `cd &&`**, rate limiting de SSH | Fase 9 |
| `inertia-json-crudo` | El parche de `no-store` en `HandleInertiaRequests` **y** el chequeo que Centinela le corre a los demás | Fase 1 (propio) y 4 (sonda) |
| `adaptar-a-pwa` | Manifest, `sw.js`, captura de `beforeinstallprompt` en el `<head>`, `.htaccess` con `no-cache`, las 3 cachés del ícono | Fase 8 (propio) y 4 (sonda) |
| `overlays-al-navegar` | Que el menú mobile no quede tapando la pantalla al navegar; script de revisión | Fase 8 |
| `ci-linux-case-sensitivity` | `config/inertia.php` con `resources/js/pages` en minúscula, `php-version` alineado al lock, `npm run build` antes de los tests, los **tres** linters | Fase 1 |
| `proyecto-en-espanol` | Dejar el starter kit en español (locale, `lang/es`, Carbon, pantallas auth y primitivas shadcn) | Fase 1 |

Se reusa además, copiando de `huella`:

- `app/Http/Controllers/Auth/GoogleController.php`, `app/Services/IngresoConGoogleService.php`,
  `app/Support/CuentaDeGoogle.php` y la migración `add_google_oauth_to_users_table` (columna
  `google_id`, 64, nullable, unique).
- `docs/google-oauth.md` como base del trámite en Google Cloud (cambiando nombre y redirect).
- El patrón de servir archivos privados de `app/Http/Controllers/AdjuntoController.php::mostrar`
  (disco privado + `Gate::authorize` + `StreamedResponse`, nunca URL pública).
- El criterio de mailables **sin `ShouldQueue`** disparados por el scheduler
  (`c:\laragon\www\huella\CLAUDE.md`, sección Recordatorios) y `GMAIL_SMTP_CONFIG.md` de
  `milarepa` para el SMTP.

## Modelo de datos

`proyectos` — id, nombre, slug, `url` (canónica), `repo_url` nullable, `perfil` (enum), `activo`,
`palabra_clave` nullable, `intervalo_minutos` (default 15), `notas`, `orden`, timestamps, softdeletes.

`chequeos` — id, `proyecto_id`, `tipo` (enum), `estado` (enum `ok|advertencia|falla`),
`codigo_http` nullable, `latencia_ms` nullable, `detalle` (json), `mensaje`, `ejecutado_at`.
Índice `(proyecto_id, tipo, ejecutado_at)`.

`incidentes` — id, `proyecto_id`, `tipo`, `abierto_at`, `cerrado_at` nullable, `fallos_seguidos`,
`ultimo_detalle`, `avisado_at`. **Idempotente por `proyecto_id` + `tipo`**: como máximo un
incidente abierto por par, misma mecánica que los recordatorios de huella. Un incidente se abre
al **segundo** fallo seguido (un hipo de red no despierta a nadie) y se cierra al primer `ok`.

`documentos` — id, `proyecto_id`, `titulo`, `slug`, `formato` (enum `md|pdf`), `ruta` (disco
privado), `nombre_original`, `tamano`, `hash` (sha256), `texto` (longtext, solo para `md`),
`orden`, timestamps, softdeletes. El `hash` hace que resubir el mismo archivo sea un no-op y que
resubirlo cambiado actualice la fila en lugar de duplicarla.

Enums PHP para los cuatro ENUM del esquema: `PerfilTecnico`, `TipoChequeo`, `EstadoChequeo`,
`FormatoDocumento`. **Al sumar un caso hay que ensanchar el ENUM de MySQL en una migración**:
sqlite no valida ENUM, los tests pasan y producción revienta con un 500 al primer guardado
(trampa documentada en el `CLAUDE.md` de huella).

`PerfilTecnico` decide qué sondas aplican: `inertia_pwa`, `inertia`, `spa_pwa`, `php_clasico`,
`estatico`. No se carga a mano: `centinela:detectar-perfil` lo infiere pegándole al sitio
(header `X-Inertia` en la respuesta → Inertia; `/manifest.webmanifest` con 200 → PWA).

### Semilla

Los 12 sitios que hoy responden, cargados activos:

| URL | Hoy |
|---|---|
| escribelo · huella · mantreando · movieboxd · secretos · primeraweb1998 (`.pablomandile.com.ar`) | 200 |
| bioinfo · mibilletera · hoytrasnoche (`.pablomandile.com.ar`) | 302 → `/login` |
| `pablomandile.com.ar` · `agendaflex.com.ar` · `localia.com.ar` | 200 |

Y cargados **inactivos**, porque hoy no resuelven: `docbrainer`, `meditarenzn`, `milarepa`,
`dharmify`. Un sitio inactivo se lista en gris y no genera chequeos ni avisos.

Ojo con dos casos: `agendaflex.com.ar` es **dominio propio** y se despliega por git, no por copia
(ver el skill); y la raíz de varios proyectos redirige con 302 a `/login`, así que **seguir
redirects es obligatorio** — sin eso el chequeo marca caído un sitio sano, que es exactamente el
falso negativo que el skill de deploy advierte con el `curl -L`.

## Motor de chequeos

Una clase por chequeo, todas detrás de la misma interfaz, y **un solo lugar** que decide cuál
aplica a qué proyecto. Es lo que evita el flujo repetido: hoy cada verificación vive suelta en un
skill distinto y se corre a mano.

```
app/Sondas/Sonda.php                  interfaz: tipo(), aplicaA(Proyecto), ejecutar(Proyecto): Resultado
app/Sondas/RegistroDeSondas.php       colección de sondas; filtra por perfil
app/Sondas/SondaDisponibilidad.php
app/Sondas/SondaCertificado.php
app/Sondas/SondaRedireccionHttps.php
app/Sondas/SondaCacheInertia.php
app/Sondas/SondaCabecerasPwa.php
app/Sondas/SondaBundle.php
app/Services/EjecutorDeChequeos.php   corre las sondas aplicables, persiste, abre/cierra incidentes, avisa
```

La interfaz se llama `Sonda` y no `Chequeo` a propósito: `Chequeo` es el **modelo** del resultado
guardado. Dos cosas distintas con el mismo nombre garantizan una confusión.

| Sonda | Qué verifica | Falla / advierte |
|---|---|---|
| `SondaDisponibilidad` | GET siguiendo hasta 5 redirects, timeout 15 s. Guarda código final, latencia, cadena de redirects y si `palabra_clave` aparece en el HTML | Falla si el código final no es 2xx o falta la palabra. Advierte si la latencia > 3 s |
| `SondaCertificado` | Días hasta el vencimiento del TLS (`stream_socket_client` con `capture_peer_cert`) | Advierte < 21 días, falla < 7 |
| `SondaRedireccionHttps` | Que `http://` conteste 301 a `https://` | Falla si sirve contenido en plano |
| `SondaCacheInertia` | Saca la versión del asset del propio 409, pide con `X-Inertia: true` y exige `Cache-Control: no-store` en el JSON **y que el HTML no lo tenga** (o se pierde el bfcache). Reporta si el CDN borró el `Vary` pidiendo con `Accept-Encoding: br` | Falla si el JSON no trae `no-store`; advierte si el HTML sí lo trae |
| `SondaCabecerasPwa` | `sw.js` y `manifest.webmanifest` con `no-cache`; manifest como `application/manifest+json`; íconos 192 y 512 con 200. Guarda `x-hcdn-cache-status` como dato | Falla si el manifest o los íconos dan 404; advierte por caché de 7 días o MIME incorrecto |
| `SondaBundle` | Extrae `app-*.js` del HTML y comprueba que el asset responda 200. Guarda el hash para ver cuándo cambió | Falla si el bundle referenciado da 404 (deploy a medio camino) |

Las últimas tres son los skills convertidos en código. `SondaCacheInertia` es el chequeo del
skill `inertia-json-crudo` §3 y `SondaCabecerasPwa` el de `adaptar-a-pwa` §7-8.

Comandos:

- `centinela:chequear {--proyecto=} {--tipo=}` — corre lo que está vencido según
  `intervalo_minutos`. Sin argumentos, todos los activos.
- `centinela:podar` — borra `chequeos` de más de 90 días. En hosting compartido una tabla que
  crece sin techo termina siendo el problema.
- `centinela:detectar-perfil {proyecto?}`.

Scheduler: disponibilidad cada 15 minutos; certificado, PWA, Inertia y bundle una vez al día;
poda semanal. **Sin worker de cola**: los mailables no llevan `ShouldQueue` y los dispara el
scheduler, que ya es asíncrono — en hosting compartido un worker se cae en silencio y deja los
avisos sin enviar sin que nadie se entere.

Riesgo despejado: el PHP de Hostinger sí puede salir a internet — el `TmdbClient` de movieboxd
(`app/Services/Tmdb/TmdbClient.php:143`) corre en producción contra la API de TMDB.

## Documentación por proyecto

- Subida desde la web, uno o varios archivos a la vez, `.md` y `.pdf`. Van al **disco privado** y
  se sirven por controlador con `Gate::authorize`, nunca por URL pública (patrón de
  `AdjuntoController::mostrar`).
- Al subir un `.md` se extrae el texto plano a `documentos.texto` para el buscador. Búsqueda con
  `LIKE` sobre texto normalizado (sin acentos), **no** `FULLTEXT`: los tests corren en sqlite, que
  no lo soporta, y el volumen real son unos pocos MB.
- Visor de `.md` renderizado con `Str::markdown()` (CommonMark, ya disponible), con índice de
  encabezados al costado. Los `.pdf` se abren o descargan; no van al visor de imágenes.
- Exportaciones con DomPDF: un `.md` a PDF, y **dossier por proyecto** (portada, índice y todos
  los `.md` del proyecto en un solo PDF). Los `.pdf` subidos se listan con su enlace pero **no** se
  concatenan: DomPDF no une PDFs y meter `fpdi` no se justifica en v1.

## Auth

`GoogleController` + `IngresoConGoogleService` + `CuentaDeGoogle` copiados de huella, con las
decisiones que ya trae ese código: se reconoce por el `sub` de Google y no por el email, se exige
email verificado por Google, y sin credenciales en el `.env` las rutas dan 404 y el botón no
aparece (permite desplegar antes de dar de alta el proyecto en Google Cloud).

Encima de eso, lo propio de Centinela: **allowlist**. `CENTINELA_EMAILS` en el `.env`; un email
fuera de la lista vuelve al login con un cartel neutro y **no** se crea el usuario. Registro,
reset de contraseña y 2FA se sacan de `config/fortify.php`. El login con contraseña queda
habilitado como puerta de emergencia por si Google falla; no hay pantalla para crearse una cuenta.

Redirects a registrar en Google Cloud: `https://centinela.pablomandile.com.ar/auth/google/callback`
y `http://localhost:8000/auth/google/callback`, carácter por carácter.

## Pantallas

| Ruta | Qué muestra |
|---|---|
| `/` | Tablero: una tarjeta por proyecto con semáforo, latencia, último chequeo e incidentes abiertos. Una columna en celular, grilla en escritorio |
| `/proyectos/{slug}` | Estado de cada sonda con su detalle, historial de latencia (chart.js + vue-chartjs, como huella), incidentes y los documentos del proyecto |
| `/proyectos/{slug}/documentos/{documento}` | Visor de `.md` con índice; los `.pdf` van a descarga |
| `/documentos` | Buscador global sobre el texto de los `.md` |
| `/ajustes/proyectos` | Alta y edición: URL, perfil (con botón «detectar»), palabra clave, intervalo, activo |
| `/salud` | JSON público y sin datos sensibles, para el monitor externo |

Reglas de frontend heredadas del `CLAUDE.md` de huella: páginas Inertia en `resources/js/pages`
**siempre en minúscula**; reusar `resources/js/components/ui/` antes de escribir un componente
nuevo; estado por props de Inertia y composables, sin Pinia; todo lo que escribe va por Inertia.

Texto en **español rioplatense con voseo**; tablas, modelos y servicios en español, salvo `users`
y sus columnas base.

Centinela no puede vigilarse a sí mismo. `/salud` existe para que un servicio externo gratuito
(healthchecks.io o UptimeRobot) lo haga; se configura al final de la fase 9.

## PWA

Todo el skill `adaptar-a-pwa`: manifest con íconos 192/512 + maskable, `beforeinstallprompt`
capturado con script inline en el `<head>` (**no** en `onMounted`, que es el error que hace perder
más tiempo), botón que se esconde con `display-mode: standalone`, instructivo manual para iOS,
`sw.js` con `cache-first` **solo** para `/build/` y `.htaccess` con `no-cache` para `sw.js` y el
manifest más `AddType application/manifest+json .webmanifest`.

En el `sw.js` va también la red de seguridad del skill `inertia-json-crudo` §5, con las tres
condiciones completas (`request.mode !== 'navigate'`, el header `X-Inertia` de la respuesta, y la
rama `redirected`): sacarle cualquiera de las tres rompe la navegación de la SPA.

Y antes de dar la PWA por buena, la revisión de overlays: en celular el menú de la sidebar es un
sheet a pantalla completa que la navegación de Inertia no desmonta.

## Deploy

Subdominio `centinela.pablomandile.com.ar` sobre
`~/domains/pablomandile.com.ar/centinela/public`. **Verificar dónde quedó el docroot** después de
crearlo en hPanel: Hostinger a veces lo apunta adentro del public del sitio principal (pasó con
movieboxd) y hay que reemplazar esa carpeta por un symlink relativo.

El server no tiene Node: se compila local y se copia `public/build`. Deploy con el script del
skill:

```bash
URL=https://centinela.pablomandile.com.ar \
  ~/.claude/skills/deploy-hostinger/scripts/deploy.sh centinela \
  public/manifest.webmanifest public/sw.js public/icons public/.htaccess app routes config database
```

Un solo cron en hPanel, con **ruta absoluta a artisan y sin `cd &&`** (con `cd` el cron figura
creado y nunca corre):

```
/opt/alt/php84/usr/bin/php /home/<CUENTA>/domains/pablomandile.com.ar/centinela/artisan schedule:run
```

No hace falta el cron del worker: no hay cola.

## Fases de trabajo

| # | Fase | Entrega |
|---|---|---|
| 0 | Revisión de skills | `CLAUDE.md` del proyecto con las decisiones cerradas y el mapa de la tabla de arriba |
| 1 | Andamiaje | Starter kit Vue, español, DomPDF, `HandleInertiaRequests` con `no-store` desde el día uno, `config/inertia.php` en minúscula, Pint + ESLint + Prettier, workflows de CI con PHP 8.4 y `npm run build` antes de los tests |
| 2 | Auth | Google + allowlist, Fortify recortado, `docs/google-oauth.md` |
| 3 | Proyectos | Migraciones, modelos, enums, Policies, seeder con los 16, CRUD y `detectar-perfil` |
| 4 | Motor de chequeos | `Sonda` + las 6 sondas + `RegistroDeSondas` + `EjecutorDeChequeos` + `centinela:chequear`, con tests por sonda usando `Http::fake()` |
| 5 | Tablero | Dashboard, detalle de proyecto, gráfico de latencia |
| 6 | Incidentes | Apertura al segundo fallo, cierre al recuperarse, mail de caída y de recuperación |
| 7 | Documentos | Subida, visor de `.md`, servido privado de `.pdf`, buscador, PDF y dossier |
| 8 | PWA | Manifest, íconos, `sw.js`, botón de instalar, `.htaccess`, revisión de overlays |
| 9 | Deploy | Subdominio, `.env` de producción, migraciones, cron de hPanel, monitor externo, `docs/deploy.md` |

Fases 1-4 son el camino crítico: con la 4 terminada Centinela ya sirve, aunque se lo corra a mano
desde la consola.

## Verificación

**Local, en cada fase**

```bash
php artisan test                 # Pest 4
npm run lint:check && npm run format:check && npm run types:check
npm run build                    # y correr los TRES linters antes de pushear, no dos
```

**Las sondas** — un test por sonda con `Http::fake()`, cubriendo 200, 302 a `/login` (que **no**
es una falla), 500, timeout, certificado por vencer, `no-store` ausente y manifest con MIME
equivocado. Más una corrida real contra producción, que es la prueba de que las sondas leen bien
sitios de verdad:

```bash
php artisan centinela:chequear --proyecto=huella -v
php artisan centinela:chequear          # los 12 activos: tabla con estado por sonda
```

El resultado esperado no es «todo verde»: si alguna sonda encuentra un `sw.js` cacheado o un
Inertia sin `no-store` en otro proyecto, **eso es el producto funcionando**.

**Cabeceras propias** — el test de regresión del skill `inertia-json-crudo` §7 (el JSON con
`no-store`, el HTML sin él), más la prueba a mano: navegar dos o tres pantallas, descartar la
pestaña en `chrome://discards` y volver; tiene que aparecer la app, no el JSON.

**PWA y overlays**

```bash
node ~/.claude/skills/adaptar-a-pwa/scripts/check-pwa.mjs https://centinela.pablomandile.com.ar/login
node ~/.claude/skills/overlays-al-navegar/scripts/revisar-overlays.mjs http://127.0.0.1:8000 --email=... --password=...
```

`installabilityErrors` tiene que venir vacío **en producción**, no solo en local.

**Después del deploy**

```bash
curl -sL https://centinela.pablomandile.com.ar/ | grep -oE 'app-[A-Za-z0-9_-]+\.js' | head -1   # el -L no es opcional
curl -sI https://centinela.pablomandile.com.ar/manifest.webmanifest | head -1
curl -sI https://centinela.pablomandile.com.ar/sw.js | grep -iE 'cache-control|x-hcdn-cache-status'
curl -s  https://centinela.pablomandile.com.ar/salud
```

**El cron** — la trampa silenciosa: figura creado y no corre. Se confirma mirando que aparezcan
filas nuevas en `chequeos` en el minuto siguiente al tick, no confiando en la pantalla de hPanel.

## Riesgos

- **Cuota de disco** del plan compartido: los documentos van al disco privado del server. Con
  `.md` y PDFs de texto es despreciable; conviene un tope por archivo (10 MB) en la validación.
- **DomPDF y el Markdown**: tablas anchas y bloques de código largos se desarman. El dossier va
  con estilo sobrio y ancho fijo, pensado para leerse impreso.
- **Rate limiting de SSH** en Hostinger: agrupar los comandos del deploy en una sola conexión por
  tanda y espaciar chequeos manuales ≥ 60 s.
- **Producción adelante del repo**: `public/build` está gitignoreado. El script de deploy aborta
  si detecta componentes que viven solo en producción; no forzar con `DEPLOY_FORCE=1` sin mirar.
