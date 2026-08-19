<?php

namespace App\Sondas;

use App\Enums\TipoChequeo;
use App\Models\Proyecto;
use App\Sondas\Soporte\LectorDeCertificado;
use Illuminate\Support\Facades\Config;

/**
 * ¿Cuánto le queda al certificado?
 *
 * Hostinger renueva Let's Encrypt solo, pero "solo" incluye fallar en silencio: un
 * subdominio creado a mano, un DNS que apuntaba a otro lado durante la renovación,
 * y el certificado vence sin que nadie se entere hasta que el navegador tira la
 * pantalla roja. Como los de Let's Encrypt duran 90 días, avisar a los 21 deja
 * tiempo de sobra para arreglarlo sin apuro.
 */
class SondaCertificado implements Sonda
{
    public function __construct(private readonly LectorDeCertificado $lector) {}

    public function tipo(): TipoChequeo
    {
        return TipoChequeo::Certificado;
    }

    public function aplicaA(Proyecto $proyecto): bool
    {
        return $proyecto->esHttps();
    }

    public function ejecutar(Proyecto $proyecto): Resultado
    {
        $certificado = $this->lector->leer($proyecto->host());

        if ($certificado === null) {
            return Resultado::falla('No se pudo leer el certificado del host.');
        }

        $dias = $certificado->diasQueLeQuedan();

        $detalle = [
            'valido_hasta' => $certificado->validoHasta->toIso8601String(),
            'dias' => $dias,
            'emisor' => $certificado->emisor,
            'nombre' => $certificado->nombre,
        ];

        $avisa = (int) Config::get('centinela.umbrales.certificado_advertencia', 21);
        $falla = (int) Config::get('centinela.umbrales.certificado_falla', 7);

        if ($dias < 0) {
            return Resultado::falla(
                'El certificado venció hace '.abs($dias).' días.',
                detalle: $detalle,
            );
        }

        if ($dias <= $falla) {
            return Resultado::falla(
                "El certificado vence en {$dias} días.",
                detalle: $detalle,
            );
        }

        if ($dias <= $avisa) {
            return Resultado::advertencia(
                "El certificado vence en {$dias} días.",
                detalle: $detalle,
            );
        }

        return Resultado::ok(
            "El certificado vence en {$dias} días.",
            detalle: $detalle,
        );
    }
}
