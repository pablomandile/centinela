# Deploy de Centinela

Hostinger compartido, subdominio `centinela.pablomandile.com.ar`, carpeta remota
`centinela`. El detalle general del flujo y sus trampas está en el skill
`deploy-hostinger`; acá va solo lo propio de este proyecto.

El server **no tiene Node**: se compila local y se copia `public/build`.

## Antes del primer deploy (una sola vez, y lo hace el usuario)

### 1. El subdominio

hPanel → Dominios → Subdominios → `centinela`.

⚠️ **Verificar dónde quedó el docroot.** Hostinger a veces lo apunta a una carpeta
adentro del public del sitio principal en vez de a `centinela/public` (pasó con
movieboxd). Se descubre buscando dónde dejó su `default.php`:

```bash
ssh agendaflex 'grep -rl "Página por defecto" ~/domains ~/public_html 2>/dev/null'
```

Si quedó en el lugar equivocado, se reemplaza esa carpeta por un symlink **relativo**
al public del proyecto, guardando antes el `default.php`:

```bash
ssh agendaflex 'cd ~/domains/pablomandile.com.ar/pablomandile/public \
  && mkdir -p ~/centinela_bak && mv centinela/default.php ~/centinela_bak/ 2>/dev/null; \
  rm -rf centinela && ln -s ../../centinela/public centinela && readlink -f centinela'
```

### 2. La base de datos

hPanel → Bases de datos MySQL. Anotar nombre, usuario y contraseña: van al `.env`.

### 3. El `.env` de producción

Se crea **en el server** y no se sube nunca desde local (el local tiene
`APP_ENV=local` y credenciales distintas). Lo que cambia respecto de `.env.example`:

```env
APP_NAME=Centinela
APP_ENV=production
APP_DEBUG=false
APP_URL=https://centinela.pablomandile.com.ar
APP_KEY=            # se genera en el server: artisan key:generate

APP_LOCALE=es
APP_FALLBACK_LOCALE=es
APP_FAKER_LOCALE=es_AR

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=<CUENTA>_centinela
DB_USERNAME=<CUENTA>_centinela
DB_PASSWORD=…

# Sin worker de cola: los mails los manda el propio comando del scheduler.
QUEUE_CONNECTION=sync
SESSION_DRIVER=database
CACHE_STORE=database

# Gmail con contraseña de aplicación (ver milarepa/GMAIL_SMTP_CONFIG.md).
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=…@gmail.com
MAIL_PASSWORD=…                       # los 16 caracteres, sin espacios
MAIL_SCHEME=tls
MAIL_FROM_ADDRESS=…@gmail.com
MAIL_FROM_NAME="${APP_NAME}"

GOOGLE_CLIENT_ID=…apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=…
# GOOGLE_REDIRECT_URI **no va acá**: se arma sobre APP_URL, que ya es https.

CENTINELA_EMAILS=tu-email@ejemplo.com
CENTINELA_AVISOS_A=tu-email@ejemplo.com
```

### 4. El redirect en Google Cloud

Tiene que estar `https://centinela.pablomandile.com.ar/auth/google/callback`, carácter
por carácter. Ver [google-oauth.md](google-oauth.md).

## El deploy

Desde la raíz del proyecto local:

```bash
npm run build

URL=https://centinela.pablomandile.com.ar \
DEPLOY_KEY=~/.ssh/agendaflex_deploy \
  ~/.claude/skills/deploy-hostinger/scripts/deploy.sh centinela \
  public/manifest.webmanifest public/sw.js public/offline.html public/.htaccess \
  public/icons public/apple-touch-icon.png \
  app bootstrap config database lang resources/views routes artisan composer.json composer.lock
```

La clave `agendaflex_deploy` sirve para **toda** la cuenta, no solo para agendaflex.

### La primera vez, además

```bash
R=~/domains/pablomandile.com.ar/centinela
PHP=/opt/alt/php84/usr/bin/php     # el `php` del CLI es 8.1 y no alcanza para Laravel 13

ssh agendaflex "cd $R && $PHP /usr/local/bin/composer install --no-dev --optimize-autoloader"
ssh agendaflex "$PHP $R/artisan key:generate --force"
ssh agendaflex "$PHP $R/artisan migrate --force"
ssh agendaflex "$PHP $R/artisan db:seed --class=ProyectosSeeder --force"
ssh agendaflex "$PHP $R/artisan storage:link"
ssh agendaflex "$PHP $R/artisan config:cache"
```

`db:seed` con `ProyectosSeeder` es idempotente (`firstOrCreate` por slug), así que se
puede volver a correr cuando aparezca un proyecto nuevo sin pisar lo editado a mano.

### Cada vez que se toca `config/` o el `.env`

```bash
ssh agendaflex "/opt/alt/php84/usr/bin/php ~/domains/pablomandile.com.ar/centinela/artisan config:cache"
```

Sin esto la app sigue leyendo la configuración vieja. Es la causa más común de "puse
las credenciales de Google y el botón no aparece".

### Cada vez que se agrega o se cambia una ruta

```bash
ssh agendaflex "/opt/alt/php84/usr/bin/php ~/domains/pablomandile.com.ar/centinela/artisan route:cache"
```

**Producción tiene la caché de rutas** (`bootstrap/cache/routes-v7.php`, que dejó el
`optimize` de la instalación). Mientras ese archivo existe, la tabla de rutas está
congelada: se puede subir el controlador y el `routes/web.php` nuevos, verlos en el
server con el `grep`, y la ruta **contesta 404 igual**. Pasó con `/acerca`: los
archivos estaban, el bundle era el nuevo, y el 404 no lo explicaba nada.

Dos consecuencias:

- Un deploy que agrega rutas se termina con `route:cache`, no con `config:cache`.
- **No puede haber rutas con closure.** `route:cache` corta con
  `Your route files contain a closure`, y el arreglo urgente —borrar el archivo de
  caché— deja producción sin ese optimizado y sin que quede registrado por qué.

## El cron

hPanel → Avanzado → Cron Jobs. **Uno solo**, cada minuto:

```
/opt/alt/php84/usr/bin/php /home/<CUENTA>/domains/pablomandile.com.ar/centinela/artisan schedule:run
```

⚠️ **Sin `cd ... &&`.** El ejecutor de cron de hPanel no pasa el comando por un shell:
el `cd` muere y nunca se llega al `php`. El síntoma es silencioso —el cron figura
creado y no corre nunca—.

**No hace falta cron de worker**: Centinela no usa cola.

### Verificar que el cron dispara de verdad

No alcanza con que hPanel lo muestre creado. Se confirma mirando que aparezcan filas
nuevas en `chequeos`:

```bash
ssh agendaflex '/opt/alt/php84/usr/bin/php ~/domains/pablomandile.com.ar/centinela/artisan tinker --execute="echo App\\Models\\Chequeo::max(\"ejecutado_at\");"'
```

Dos consultas separadas por unos minutos tienen que dar fechas distintas. Lo mismo se
ve sin SSH en `https://centinela.pablomandile.com.ar/salud`, en
`minutos_desde_el_ultimo_chequeo`.

## El monitor externo

Centinela no puede avisar que se cayó estando caído. `GET /salud` es público y
contesta JSON sin datos sensibles: alcanza con apuntarle un monitor gratuito
(healthchecks.io, UptimeRobot) cada 15 minutos.

Lo que conviene vigilar no es solo el código 200: si el scheduler se murió, la app
sigue contestando 200 y `minutos_desde_el_ultimo_chequeo` se estira. Un monitor que
sepa mirar el JSON (o un chequeo de palabra clave) atrapa las dos cosas.

## Verificación después de desplegar

```bash
# el bundle nuevo (el -L no es opcional: la raíz redirige a /login)
curl -sL https://centinela.pablomandile.com.ar/ | grep -oE 'app-[A-Za-z0-9_-]+\.js' | head -1
ls public/build/assets/ | grep -E '^app-.*\.js$'

# la PWA: el manifest con su MIME y sin caché, y el sw también
curl -sI https://centinela.pablomandile.com.ar/manifest.webmanifest | grep -iE 'content-type|cache-control'
curl -sI https://centinela.pablomandile.com.ar/sw.js | grep -iE 'cache-control|x-hcdn-cache-status'

# las cabeceras de Inertia: no-store en el XHR, y **no** en el HTML
V=$(curl -sD - -o /dev/null https://centinela.pablomandile.com.ar/login -H "X-Inertia: true" | tr -d '\r' | grep -i '^x-inertia-version' | cut -d' ' -f2)
curl -sD - -o /dev/null https://centinela.pablomandile.com.ar/login -H "X-Inertia: true" -H "X-Inertia-Version: $V" | tr -d '\r' | grep -iE '^(cache-control|content-type)'
curl -sI https://centinela.pablomandile.com.ar/login | tr -d '\r' | grep -i '^cache-control'

# el botón de Google
curl -s https://centinela.pablomandile.com.ar/login | grep -o 'googleHabilitado":[a-z]*'

# y que la app se pueda instalar, preguntándole a Chrome
node check-pwa.mjs https://centinela.pablomandile.com.ar/login   # ver más abajo
```

Para `check-pwa.mjs` hace falta `puppeteer-core` en el directorio desde el que se
corre:

```bash
mkdir -p /tmp/pwacheck && cd /tmp/pwacheck && npm init -y && npm i puppeteer-core
cp ~/.claude/skills/adaptar-a-pwa/scripts/check-pwa.mjs .
```

## Revisión de overlays (mobile)

Necesita un usuario con contraseña, que Centinela no crea por seeder a propósito. Se
crea uno al momento y se borra después:

```bash
php artisan tinker --execute="App\Models\User::updateOrCreate(['email'=>'revision@centinela.test'],['name'=>'Revisión','password'=>bcrypt('lo-que-sea'),'rol'=>App\Enums\RolUsuario::Admin,'email_verified_at'=>now()]);"

node revisar-overlays.mjs http://127.0.0.1:8000 --email=revision@centinela.test --password=lo-que-sea

php artisan tinker --execute="App\Models\User::where('email','revision@centinela.test')->delete();"
```

## Rollback

El script deja el build anterior en `~/centinela_bak_<timestamp>` en el server:

```bash
ssh agendaflex "R=~/domains/pablomandile.com.ar/centinela; \
  cd \$R/public && rm -rf build && cp -r ~/centinela_bak_<timestamp>/build build"
```

## La trampa más cara de este flujo

`public/build/` está en `.gitignore`, así que viaja por `scp` y no por git: alcanza que
alguien compile y suba sin commitear el fuente para que **producción tenga frontend que
el repo no tiene**, y el próximo deploy lo pise en silencio. El script del skill lo
chequea en su paso `0/5` y aborta; no forzar con `DEPLOY_FORCE=1` sin mirar qué
encontró.
