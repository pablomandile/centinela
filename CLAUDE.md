# Centinela — convenciones del proyecto

Herramienta personal de un solo usuario con dos módulos:

1. **Salud** — vigila los sitios ya publicados (`*.pablomandile.com.ar`, más
   `agendaflex.com.ar` y `localia.com.ar`) y avisa por mail cuando uno se cae o se
   recupera.
2. **Documentación** — agrupa por proyecto los `.md` y `.pdf` que hoy están sueltos en
   las raíces de `c:\laragon\www`, con visor, buscador y exportación a PDF.

El plan de trabajo completo está en [docs/plan.md](docs/plan.md). Ante una discrepancia
entre ese documento y este archivo, **manda este archivo**.

## Stack (versiones reales instaladas)

Laravel 13.26 · PHP 8.4 · MySQL 8 · Inertia 3.3 · Vue 3 + TypeScript · Tailwind 4 · Vite
Starter kit Vue oficial. Auth por **Fortify**, recortado (ver más abajo).
Rutas tipadas con **Wayfinder**, no con Ziggy.
Tests con **Pest 4.7** (PHPUnit 12 — **no** subir a Pest 5, que exige PHPUnit 13 y rompe
el kit).
Estático: Pint + PHPStan/Larastan nivel 7 + ESLint + Prettier + vue-tsc.
Agregados sobre el kit: `laravel/socialite`, `barryvdh/laravel-dompdf`.

## Decisiones cerradas (no volver a discutirlas)

| Tema | Decisión |
|------|----------|
| Documentos | **Subida manual desde la web** (`.md` y `.pdf`). Sin CLI de sync ni pull de GitHub. |
| Login | **Google OAuth** con allowlist de emails en `.env` (`CENTINELA_EMAILS`). Registro cerrado. |
| Chequeos | Básicos **+ auditoría técnica derivada de los skills**. Sin PageSpeed. |
| Avisos | **Email al caer y al recuperarse**, disparado por el scheduler. Sin worker de cola. |
| App | Una sola app web responsive, **PWA instalable**. Sin Electron ni Capacitor. |

**Fuera de alcance:** audios / OneDrive / rclone, sección pública sin login, invitados,
PageSpeed Insights, sync automático de docs, versionado de documentos, unir PDFs subidos
dentro del dossier.

Es de un solo usuario, pero `users` lleva `rol` desde la primera migración y todo pasa por
Policies: invitar a alguien más adelante es una fila y un caso en la Policy, no rehacer la
autorización.

## Idioma

- Todo el texto visible en **español rioplatense, con voseo**. Nada de "usted".
- Tablas, columnas, modelos, rutas y servicios **en español**.
- **Excepción:** `users` y sus columnas base (`name`, `email`, `password`) quedan como las
  genera el starter kit.

## Fechas

- Se persiste **en UTC** (el default de Laravel; no se toca `config/app.php`).
- La conversión a hora local la hace **dayjs en el navegador**, no el backend: la app se
  lee bien desde cualquier zona y no hay una zona horaria por usuario que mantener. Es a
  propósito distinto de huella, que sí es multiusuario.

## Backend

- Un **FormRequest por acción de escritura**. Nada de validación en el controlador.
- **Policy en todo modelo**; `Gate::authorize()` siempre.
- Los **servicios** contienen la lógica; los controladores solo orquestan.
- Enums de PHP para todos los ENUM del esquema.
- Todo listado con **eager loading explícito**.
- Soft deletes en `proyectos` y `documentos`.
- Documentos en el disco **privado**, servidos por controlador tras autorizar. Nunca por
  URL pública. Patrón: `AdjuntoController::mostrar` de huella.

### Las tres trampas de sqlite vs MySQL

Los tests corren en **sqlite en memoria** y producción es **MySQL 8**. Tres divergencias
conocidas, todas con el mismo síntoma: el test pasa y producción tira 500.

1. **Al sumar un caso a un enum de PHP hay que ensanchar el ENUM de MySQL** en una
   migración. sqlite no valida ENUM.
2. **`Rule::unique` sobre una columna `date` no es portable**: MySQL trunca, sqlite guarda
   `00:00:00` y la comparación por igualdad nunca encuentra el duplicado. Usar una regla
   de cierre con `whereDate`.
3. **`FULLTEXT` no existe en sqlite.** La búsqueda de documentos va con `LIKE` sobre texto
   normalizado (sin acentos); el volumen real son unos pocos MB.

## Motor de chequeos

Una clase por chequeo detrás de una sola interfaz, y **un solo lugar** que decide cuál
aplica a qué proyecto:

```
app/Sondas/Sonda.php                 interfaz: tipo(), aplicaA(Proyecto), ejecutar(Proyecto)
app/Sondas/RegistroDeSondas.php      las seis sondas; filtra con aplicaA()
app/Sondas/Sonda*.php                una por chequeo
app/Sondas/Soporte/HacePedidos.php   el pedido HTTP compartido (timeout, redirects, UA)
app/Sondas/Soporte/LectorDeCertificado.php  el único que no pasa por el cliente HTTP
app/Services/EjecutorDeChequeos.php  persiste, abre/cierra incidentes, avisa
app/Services/DetectorDePerfil.php    le pregunta al sitio qué usa
```

- La interfaz se llama **`Sonda`** y el modelo del resultado guardado **`Chequeo`**. Son
  dos cosas distintas: no unificar los nombres.
- **Seguir redirects es obligatorio.** La raíz de varios proyectos contesta 302 a
  `/login`: tratar eso como caída es el falso negativo más fácil de cometer acá.
- Los **incidentes son idempotentes por `proyecto_id` + `tipo`**, se abren al **segundo**
  fallo seguido y se cierran al primer `ok`. Los fallos seguidos se cuentan leyendo el
  historial de `chequeos`, no con un contador aparte que se pueda desincronizar.
- Solo `falla` abre incidentes. Una **advertencia** —certificado a 15 días, sitio lento,
  `sw.js` cacheado— se ve en el tablero y no despierta a nadie, pero **cierra** un
  incidente abierto: la falla se resolvió aunque quede algo por mirar.
- El mail se manda **después** de la transacción, nunca adentro: un SMTP lento mantendría
  la fila bloqueada y uno que falla haría rollback de un incidente que sí ocurrió.
- Los mailables **no** llevan `ShouldQueue`: los dispara un comando del scheduler, que ya
  es asíncrono. Encolarlos obligaría a un worker, y en hosting compartido eso se cae en
  silencio y deja los avisos sin enviar sin que nadie se entere.
- `centinela:podar` recorta `chequeos` a 90 días, por lotes. Una tabla que crece sin techo
  es un problema real en hosting compartido.

### Las tres banderas, y por qué no son un enum

`proyectos` guarda `usa_inertia`, `es_pwa` y `tiene_bundle`, y cada sonda pregunta por la
que le importa. Empezó siendo un enum `PerfilTecnico` con casos combinados
(`inertia_pwa`, `spa_pwa`, …) y **la primera corrida real lo rompió**: hoytrasnoche es PHP
sin build **y** tiene manifest válido, o sea que no entra en ninguna combinación prevista.
Un enum de perfiles crece al cuadrado; tres booleanos independientes no.

Las llena `centinela:detectar-perfil`, que **por defecto solo informa**: guardar en
silencio las banderas de dieciséis proyectos cambia qué se audita en cada uno, y eso se
notaría recién cuando algo dejara de avisar.

### Tres trampas que ya costaron un rato

1. **`X-Inertia-Version` en la respuesta lo agregó inertia-laravel 3.** La mitad de los
   proyectos de la cuenta sigue en 2 y no lo manda. La señal universal de "esto es
   Inertia" es **`X-Inertia-Location`** en el 409; la versión, cuando no viene en la
   cabecera, se saca del `data-page` del HTML.
2. **200 no alcanza para dar por bueno un manifest.** El hosting de localia contesta la
   home entera con 200 para cualquier ruta, así que la detección la daba por instalable.
   Hay que parsear el cuerpo y exigir `name` o `icons`.
3. **`now()` devuelve `CarbonImmutable`** (`Date::use()` en `AppServiceProvider`), que
   **no** es hijo de `Illuminate\Support\Carbon`. Tipar un parámetro de fecha con esa
   clase tira `TypeError`, el ejecutor lo atrapa como "falla" y el chequeo queda en rojo
   sin explicación. En firmas de fecha va `CarbonInterface`.

## Documentación

- Los archivos van al **disco privado** (`storage/app/private`) y se sirven por
  controlador tras autorizar. Nunca por URL pública: adivinar un slug no puede alcanzar
  para leer la documentación de un proyecto.
- **El hash decide si es el mismo archivo.** Resubir algo idéntico es un no-op; resubirlo
  cambiado actualiza la fila y borra el archivo viejo. Sin eso, la lista termina con
  cinco copias de README.md y ninguna dice cuál es la buena.
- **El título sale del primer `# encabezado`** del markdown, no del nombre del archivo:
  "ARCHITECTURE.md" describe mucho peor el contenido que su propio título.
- El slug es único **por proyecto**, así que las rutas de documento van dentro de
  `Route::scopeBindings()`. Sin eso, dos proyectos con un "readme" cada uno se pisan y
  la URL de uno muestra el documento del otro.
- La búsqueda compara contra **`texto_normalizado`** (título + texto, en minúsculas y sin
  acentos). No es prolijidad: MySQL ignora los acentos por collation y sqlite no, así que
  sin esa columna la búsqueda andaría distinto en producción que en los tests.
- **De los PDF no se extrae texto** y **no se concatenan** en el dossier: DomPDF no une
  PDFs. Se listan al final para que el dossier no mienta sobre lo que contiene.
- El markdown se renderiza con `Str::markdown()` y `html_input => escape`. Los `id` de
  los encabezados se inyectan sobre el HTML ya generado, no con la extensión de
  permalinks de CommonMark: esa mete un `<a>` con un símbolo adentro del encabezado.

## Frontend

- Páginas Inertia en `resources/js/pages`: **carpeta en minúscula, componente en
  PascalCase** (`proyectos/Index.vue`, `documentos/Show.vue`). Linux distingue el case.
- **Las fechas viajan en ISO 8601 UTC** y las formatea dayjs en el navegador
  (`composables/useFecha.ts`). El backend no arma strings de fecha para mostrar.
- **El semáforo nunca es solo color**: `SemaforoEstado` lleva su texto al lado o en un
  `sr-only`. Un tablero que distingue "bien" de "caído" solo por el tono del punto no se
  puede usar con daltonismo ni se entiende impreso.
- **"Sin datos" no es "bien".** Un proyecto sin chequeos está sin mirar, y se dice: gris
  propio y su propio texto.
- En el tablero, **los proyectos con algo roto van primero**. Con doce tarjetas, lo que
  requiere atención no puede depender de que uno recorra la grilla con la vista.

## PWA y overlays

- Todo lo del skill `adaptar-a-pwa` está aplicado: manifest con 192/512/maskable, el
  listener de `beforeinstallprompt` **inline en el `<head>`** (desde un componente el
  evento ya pasó), `sw.js` con cache-first solo para `/build/`, `.htaccess` con
  `no-cache` para `sw.js` y el manifest, y el botón que se esconde con la app instalada.
- El `sw.js` incluye la **red de seguridad del JSON crudo** con sus tres condiciones. Si
  se le saca cualquiera, la navegación de la SPA se rompe. Lo cuida `PwaTest`.
- **El botón de instalar está en un solo lugar** (el pie de la sidebar). Ponerlo también
  en el header mobile lo dejaría dos veces en el DOM, y cualquier verificación con
  `querySelector` agarraría el primero, que puede estar oculto.
- **`AppSidebar` cierra el sheet al navegar, solo en mobile** (`router.on('navigate')`).
  Sin eso el menú queda tapando la pantalla nueva con el `body` bloqueado: la app
  funciona perfecto y no se ve. Lo detecta
  `~/.claude/skills/overlays-al-navegar/scripts/revisar-overlays.mjs`, que ya encontró
  este bug una vez acá.
- Al cambiar un ícono hay que tocar **tres** lugares: el nombre de `CACHE` en `sw.js`, el
  `?v=` de los `<link rel="icon">` y el `?v=` del manifest. Los íconos se regeneran con
  `php scripts/generar-iconos.php public/icons`.
- Reutilizar `resources/js/components/ui/` (shadcn-vue sobre reka-ui) antes de escribir un
  componente nuevo.
- Estado por props de Inertia + composables. **Sin Pinia.**
- Todo lo que escribe va por Inertia.
- Gráficos con `chart.js` + `vue-chartjs`, y **el gráfico nunca es la única fuente**:
  siempre la lista con fechas y valores al lado.

## Skills: qué leer antes de tocar qué

Los skills viven en `~/.claude/skills/`. **Leer el que corresponde antes de empezar**, no
después de que aparezca el síntoma.

| Vas a tocar… | Leé primero |
|---|---|
| Deploy, cron de hPanel, rollback | `deploy-hostinger` |
| Manifest, service worker, íconos, botón de instalar | `adaptar-a-pwa` |
| Cabeceras de Inertia, caché del CDN | `inertia-json-crudo` |
| Menú mobile, sheets, modales | `overlays-al-navegar` |
| CI en rojo con local en verde | `ci-linux-case-sensitivity` |
| Textos del starter kit en inglés | `proyecto-en-espanol` |

### Ya aplicado en este repo (no rehacerlo)

- **`HandleInertiaRequests::handle()`** setea `no-store` en la respuesta XHR y **solo** en
  esa: en el HTML mataría el bfcache. Lo cuida `tests/Feature/CabecerasInertiaTest.php`,
  que además falla si no hay `public/build/manifest.json` — sin manifest la versión de
  asset queda vacía y el test pasaría por el motivo equivocado.
- **CI con PHP 8.4, no 8.3.** Los paquetes de Symfony del `composer.lock` exigen
  `php >=8.4.1`; con 8.3 el `composer install` corta en el paso de Setup y el job ni llega
  a los tests.
- **CI con servicio MySQL.** `composer setup` corre `artisan migrate --force` contra lo que
  dice `.env.example`, que es MySQL, aunque los tests usen sqlite.
- **`guzzlehttp/guzzle` fijado en 7.x.** Laravel 13 trae guzzle 8, pero `laravel/socialite`
  todavía pide `^6|^7`: instalarlo exige `-W` para que composer baje guzzle. Es la misma
  combinación que corre en huella. No "actualizar" guzzle a 8 sin verificar antes que
  socialite lo soporte, o se rompe el ingreso con Google.
- **`config/inertia.php` ya apunta a `resources/js/pages`** en minúscula (Inertia 3 usa
  `pages.paths` tanto en runtime como en los asserts de testing). Está bien así: no
  "corregirlo" a `Pages`.
- **`wayfinder:generate` va SIEMPRE con `--with-form`.** `vite.config.ts` pide
  `formVariants: true`, así que el front usa `.form()` en cada `<Form>`. Un
  `php artisan wayfinder:generate` pelado regenera sin esas variantes y rompe cinco
  pantallas con `Property 'form' does not exist`, sin que nada explique por qué. Lo más
  seguro es dejar que lo haga Vite (`npm run dev` / `npm run build`).
- **Fortify registra `/user/confirm-password` aunque no haya features.** No es una feature
  que se pueda apagar, así que la página `auth/ConfirmPassword.vue` tiene que existir: una
  ruta registrada sin vista responde 500 si alguien la escribe a mano.
- **`composer require/remove --no-scripts` deja el manifiesto de paquetes viejo.**
  `artisan` muere con `Class "...ServiceProvider" not found` y `package:discover` también
  —arranca la app para descubrir—. Se arregla borrando `bootstrap/cache/packages.php` y
  `services.php` antes de correrlo.
- **`laravel/chisel` ya no está.** `artisan install:features` corrió durante la instalación,
  dejó todas las features de Fortify encendidas y borró `chisel.php`. Los comentarios
  `@chisel-passkeys` que quedan en el código son restos inertes: el recorte de 2FA y llaves
  de acceso se hizo a mano (componentes, páginas, tests y props).
- **En local el ingreso con Google se prueba por `http://localhost:8000`**, no por
  `centinela.test`: Google solo acepta `http://` en localhost y rechaza los TLD que no son
  públicos. Detalle en [docs/google-oauth.md](docs/google-oauth.md).

## Tests

- Los tests corren en **sqlite en memoria** (ver las tres trampas de arriba).
- Toda sonda se prueba con `Http::fake()` y **`Http::preventStrayRequests()`**: si una URL
  no está fakeada, el test falla en vez de pegarle a un sitio real.
- **`Http::fake()` acumula stubs, no los reemplaza.** Un segundo `fake()` sobre la misma
  URL no tiene efecto, así que un test donde el sitio "se cae y después se recupera" se
  escribe con **una** respuesta que lee el estado actual del test, no con dos `fake()`.
- `Http::response($array, …)` **pisa el `Content-Type` a `application/json`**: para probar
  el MIME de un manifest hay que mandar el cuerpo como string.
- Un test que solo mira qué sondas corrieron no alcanza: si una explota, el ejecutor la
  registra como falla y el test pasa igual. Hay que exigir también el estado.

## Comandos

```bash
composer dev          # server + vite + logs
php artisan test      # Pest
composer ci:check     # eslint + prettier + vue-tsc + pint + phpstan + tests

php artisan centinela:chequear --forzar            # todas las sondas, ya
php artisan centinela:chequear --proyecto=huella   # uno solo (aunque esté inactivo)
php artisan centinela:detectar-perfil --aplicar    # ajusta las banderas de cada sitio
php artisan centinela:podar                        # recorta el historial
```

**Antes de pushear hay que correr los tres linters del frontend, no dos**: Pint y Prettier
pueden pasar y ESLint no. `composer ci:check` los corre todos.

## Deploy

Hostinger compartido, subdominio `centinela.pablomandile.com.ar`, carpeta remota
`centinela`. El server **no tiene Node**: se compila local y se copia `public/build`.
Detalle completo en [docs/deploy.md](docs/deploy.md) y en el skill `deploy-hostinger`.
