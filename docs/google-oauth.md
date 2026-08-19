# Ingreso con Google

El código está completo y probado (13 tests en `tests/Feature/Auth/IngresoConGoogleTest.php`).
Mientras `GOOGLE_CLIENT_ID` y `GOOGLE_CLIENT_SECRET` estén vacías, el botón no aparece y
las rutas devuelven 404: la app se puede desplegar antes de terminar el trámite.

Las credenciales **nunca** van en este archivo ni en `.env.example`: solo en el `.env` de
cada máquina y en el del servidor.

## Qué hay que tener en Google Cloud

En **APIs y servicios → Credenciales → ID de cliente de OAuth** (tipo *Aplicación web*),
en **URI de redireccionamiento autorizados**, tienen que estar estos dos:

```
https://centinela.pablomandile.com.ar/auth/google/callback
http://localhost:8000/auth/google/callback
```

Tienen que coincidir **carácter por carácter** con lo que manda la app, incluido el esquema
y sin barra al final. Un `http` donde va `https` da `redirect_uri_mismatch`, que es el error
más común de este trámite.

### ⚠️ En local no se puede usar `centinela.test`

Google solo acepta `http://` cuando el host es `localhost` o `127.0.0.1`, y encima rechaza
los TLD que no son públicos —`.test` entre ellos—. O sea que
`http://centinela.test/auth/google/callback` **no se puede registrar**, aunque sea la URL
con la que se trabaja todos los días en Laragon.

Por eso el `.env` local lleva el redirect explícito:

```env
GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback
```

Y para probar el ingreso hay que entrar por `http://localhost:8000` levantado con
`php artisan serve` (puerto 8000, el default), no por `centinela.test`.

En producción esa variable **no va**: el redirect se arma solo sobre `APP_URL`, que ahí es
`https://centinela.pablomandile.com.ar`.

### Pantalla de consentimiento

- Tipo de usuario: **Externo**.
- Nombre de la app: `Centinela`. Email de asistencia: el propio.
- Dominio autorizado: `pablomandile.com.ar`.
- Permisos: alcanzan los tres básicos —`userinfo.email`, `userinfo.profile` y `openid`—, que
  es exactamente lo que pide la app (verificado: `scope=openid+profile+email`). **No pedir
  nada más**: cualquier permiso extra dispara una revisión de Google que tarda semanas.
- Mientras la app esté en modo **Prueba**, solo entran los emails que figuren como usuarios
  de prueba. Con los tres permisos básicos se puede publicar sin verificación.

## Qué poner en el `.env`

```env
GOOGLE_CLIENT_ID=...apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=...
CENTINELA_EMAILS=tu-email@ejemplo.com
```

En producción, lo mismo en el `.env` del server y después **`config:cache`**, o la app sigue
leyendo la configuración vieja:

```bash
R=~/domains/pablomandile.com.ar/centinela
/opt/alt/php84/usr/bin/php $R/artisan config:cache
```

## Cómo verificar que quedó bien

```bash
# el botón aparece cuando el prop viene en true
curl -s https://centinela.pablomandile.com.ar/login | grep -o 'googleHabilitado":[a-z]*'

# y el redirect sale con el client_id y los tres scopes
curl -sD - -o /dev/null https://centinela.pablomandile.com.ar/auth/google/redirect \
  | grep -i '^location'
```

Y entrar a `/login`: tiene que estar el botón «Continuar con Google» arriba del formulario.

## Decisiones que ya están tomadas en el código

- **La allowlist manda.** `CENTINELA_EMAILS` es la única puerta: un email que no está ahí no
  entra y **no crea usuario**, aunque su cuenta de Google sea perfecta. Se evalúa en **cada**
  ingreso, no solo al crear la cuenta: sacar un email del `.env` alcanza para cerrarle la
  puerta a alguien que ya había entrado.
- **Se reconoce por el `sub` de Google, no por el email.** El email de una cuenta de Google se
  puede cambiar; el identificador no. Si cambió, se actualiza.
- **El email tiene que venir verificado por Google.** Si no, se rechaza: con un email sin
  verificar cualquiera podría reclamar una dirección habilitada declarándola como propia.
- **Una cuenta por email**: si el email ya existe en Centinela, se le vincula el `google_id`
  en vez de crear una segunda cuenta.
- **La cuenta queda sin contraseña.** Nunca eligió una, y ponerle una al azar la haría figurar
  como que puede entrar con email y clave cuando no puede. Desde *Configuración → Seguridad*
  puede definir una cuando quiera —la puerta de emergencia por si Google falla— y ahí **no**
  se le pide la anterior, porque no tiene.
- **Cancelar en la pantalla de Google no es un error**: vuelve al login sin ningún cartel rojo.
- **Los errores de Socialite no se muestran.** Traen partes de la respuesta de Google; van al
  log y el usuario ve un mensaje propio. El "no tenés acceso" de la allowlist, en cambio, ni
  siquiera va al log como warning: es un caso esperado.
