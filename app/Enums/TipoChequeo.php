<?php

namespace App\Enums;

/**
 * Los chequeos que Centinela sabe hacer.
 *
 * Los tres últimos son los skills convertidos en código: lo que hasta ahora se
 * verificaba a mano, de a un proyecto y solo cuando aparecía el síntoma.
 *
 * Ojo al sumar un caso: `chequeos.tipo` e `incidentes.tipo` son columnas ENUM
 * reales en MySQL y hay que ensancharlas en una migración.
 */
enum TipoChequeo: string
{
    case Disponibilidad = 'disponibilidad';
    case Certificado = 'certificado';
    case RedireccionHttps = 'redireccion_https';
    case CacheInertia = 'cache_inertia';
    case CabecerasPwa = 'cabeceras_pwa';
    case Bundle = 'bundle';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Disponibilidad => 'Disponibilidad',
            self::Certificado => 'Certificado',
            self::RedireccionHttps => 'Redirección a HTTPS',
            self::CacheInertia => 'Caché de Inertia',
            self::CabecerasPwa => 'Cabeceras de la PWA',
            self::Bundle => 'Bundle',
        };
    }

    /**
     * Qué mira, en una línea, para mostrar al lado del resultado.
     */
    public function descripcion(): string
    {
        return match ($this) {
            self::Disponibilidad => 'Que el sitio conteste, a tiempo y con su contenido.',
            self::Certificado => 'Cuánto le queda al certificado antes de vencer.',
            self::RedireccionHttps => 'Que http:// mande a https:// y no sirva en plano.',
            self::CacheInertia => 'Que el XHR de Inertia no se pueda guardar, y el HTML sí.',
            self::CabecerasPwa => 'Que el service worker y el manifest no queden cacheados.',
            self::Bundle => 'Que el JS que pide la página exista de verdad.',
        };
    }

    /**
     * Cada cuánto tiene sentido correrlo.
     *
     * La disponibilidad va seguido; el resto cambia solo cuando hay un deploy, y
     * pegarle cada quince minutos a doce sitios para mirar lo mismo sería gastar
     * el rate limit del hosting sin ganar nada.
     */
    public function esFrecuente(): bool
    {
        return $this === self::Disponibilidad;
    }
}
