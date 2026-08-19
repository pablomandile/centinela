<?php

namespace App\Services;

use App\Enums\RolUsuario;
use App\Exceptions\AccesoNoHabilitado;
use App\Models\User;
use App\Support\CuentaDeGoogle;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Alta y vínculo de cuentas que entran con Google.
 *
 * Dos reglas de fondo:
 *
 * 1. **La allowlist manda.** Centinela no tiene registro: un email que no está
 *    en `centinela.emails_habilitados` no entra y **no crea usuario**. Sin esto,
 *    cualquiera con una cuenta de Google tendría acceso al tablero.
 * 2. **El email identifica a la persona.** Google ya lo verificó, así que si
 *    coincide con una cuenta que existe es la misma persona y se le vincula el
 *    `google_id` en vez de crear una segunda cuenta.
 */
class IngresoConGoogleService
{
    /**
     * ¿Está configurado el ingreso con Google?
     *
     * Se pregunta antes de mostrar el botón y en las rutas: sin credenciales, la
     * app tiene que funcionar como si la opción no existiera, no tirar un 500.
     * Es lo que permite desplegar el código antes de hacer el trámite en Google
     * Cloud.
     */
    public static function configurado(): bool
    {
        // `Config::get` y no `Config::string`: sin las variables en el .env el
        // valor es null, y `Config::string` lo toma como un tipo inválido y tira
        // excepción en vez de devolver el default.
        return filled(Config::get('services.google.client_id'))
            && filled(Config::get('services.google.client_secret'));
    }

    /**
     * ¿Este email tiene permitido entrar?
     */
    public static function habilitado(string $email): bool
    {
        /** @var list<string> $habilitados */
        $habilitados = Config::array('centinela.emails_habilitados');

        return in_array(mb_strtolower(trim($email)), $habilitados, strict: true);
    }

    /**
     * El usuario de Centinela detrás de una cuenta de Google, creándolo si hace
     * falta.
     *
     * @throws AccesoNoHabilitado si el email no está en la allowlist
     * @throws RuntimeException si Google no devolvió lo mínimo necesario
     */
    public function resolver(CuentaDeGoogle $cuenta): User
    {
        if (blank($cuenta->id) || blank($cuenta->email)) {
            // Sin email no hay forma de vincular la cuenta ni de avisarle nada.
            throw new RuntimeException('Google no devolvió el email de la cuenta.');
        }

        if (! $cuenta->emailVerificado) {
            throw new RuntimeException('La cuenta de Google no tiene el email verificado.');
        }

        // Antes de tocar la base: si no está habilitado, no se crea nada ni se
        // vincula nada.
        if (! self::habilitado($cuenta->email)) {
            throw AccesoNoHabilitado::para($cuenta->email);
        }

        return DB::transaction(function () use ($cuenta): User {
            $porGoogle = User::where('google_id', $cuenta->id)->first();

            if ($porGoogle !== null) {
                // El email en Google puede haber cambiado desde la última vez;
                // el id es el que se mantiene.
                $porGoogle->email = $cuenta->email;
                $porGoogle->save();

                return $porGoogle;
            }

            $porEmail = User::where('email', $cuenta->email)->first();

            if ($porEmail !== null) {
                $porEmail->google_id = $cuenta->id;
                $porEmail->save();

                return $porEmail;
            }

            return $this->crear($cuenta);
        });
    }

    /**
     * Cuenta nueva. Queda **sin contraseña**: nunca eligió una, y guardarle una
     * al azar la haría figurar como que puede entrar con email y clave.
     */
    private function crear(CuentaDeGoogle $cuenta): User
    {
        $usuario = new User;

        // `forceFill` y no asignación: `password` y `rol` no son fillable, y acá
        // se escriben desde el sistema y no desde un formulario.
        $usuario->forceFill([
            'name' => $cuenta->nombre ?: (string) str($cuenta->email)->before('@'),
            'email' => $cuenta->email,
            'google_id' => $cuenta->id,
            'password' => null,
            'rol' => RolUsuario::Admin,
            // Google ya verificó el email: pedirle que confirme el mismo email
            // sería hacerlo esperar un mail para nada.
            'email_verified_at' => $usuario->freshTimestamp(),
        ]);

        $usuario->save();

        return $usuario;
    }
}
