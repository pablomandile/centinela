<?php

namespace App\Sondas\Soporte;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Throwable;

/**
 * Lee el certificado TLS de un host abriendo el socket.
 *
 * Está en una clase aparte y se inyecta —no es un método privado de la sonda—
 * porque es lo único de Centinela que no pasa por el cliente HTTP: `Http::fake()`
 * no lo puede interceptar. Así los tests de la sonda cambian esta pieza por una
 * que devuelve la fecha que necesitan, sin salir a la red ni depender de que el
 * certificado de un sitio real venza en tal fecha.
 */
class LectorDeCertificado
{
    public function leer(string $host, int $puerto = 443): ?Certificado
    {
        $timeout = (int) Config::get('centinela.umbrales.timeout', 15);

        $contexto = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                // Un certificado vencido o de otro host es exactamente lo que se
                // quiere reportar, así que no se verifica: verificando, el socket
                // fallaría y no habría certificado que leer.
                'verify_peer' => false,
                'verify_peer_name' => false,
                'SNI_enabled' => true,
                'peer_name' => $host,
            ],
        ]);

        try {
            $socket = @stream_socket_client(
                "ssl://{$host}:{$puerto}",
                $codigo,
                $error,
                $timeout,
                STREAM_CLIENT_CONNECT,
                $contexto,
            );

            if ($socket === false) {
                return null;
            }

            $parametros = stream_context_get_params($socket);
            fclose($socket);

            $recurso = $parametros['options']['ssl']['peer_certificate'] ?? null;

            if ($recurso === null) {
                return null;
            }

            $datos = openssl_x509_parse($recurso);

            if ($datos === false || ! isset($datos['validTo_time_t'])) {
                return null;
            }

            return new Certificado(
                validoHasta: Carbon::createFromTimestampUTC((int) $datos['validTo_time_t']),
                emisor: $datos['issuer']['O'] ?? $datos['issuer']['CN'] ?? null,
                nombre: $datos['subject']['CN'] ?? null,
            );
        } catch (Throwable) {
            return null;
        }
    }
}
