<?php

/**
 * Genera todos los archivos de marca a partir de los dos masters de resources/img.
 *
 *   php scripts/generar-iconos.php
 *
 * Los masters son los originales y no se tocan nunca:
 *
 *   resources/img/logo.png        el escudo con el faro, sin texto  -> favicon e íconos
 *   resources/img/logotexto.png   el escudo + "Centinela" + bajada  -> la app
 *
 * Los dos vienen con **fondo transparente**, y el faro es azul marino oscuro. Sobre
 * el tema oscuro de la app la torre casi desaparece, así que:
 *
 * - Los íconos y el favicon se generan **sobre blanco**. Es como está diseñado el
 *   logo, y además iOS rellena de negro cualquier transparencia del apple-touch-icon.
 * - Los derivados para la interfaz salen transparentes: la placa clara se la pone
 *   el componente (LogoMarca/LogoTexto), donde se puede ajustar sin regenerar nada.
 *
 * Al cambiar un master hay que subir la versión en **cuatro** lugares —el `?v=` de los
 * `<link rel="icon">`, el `?v=` del manifest, el nombre de CACHE en sw.js y el `?v=` de
 * offline.html—, y el test "los archivos de marca y las cuatro referencias van con la
 * misma versión" lo verifica.
 */

/**
 * Carga un PNG con alpha.
 */
function master(string $ruta): GdImage
{
    $img = @imagecreatefrompng($ruta);

    if ($img === false) {
        fwrite(STDERR, "No pude leer {$ruta}\n");
        exit(1);
    }

    imagealphablending($img, true);
    imagesavealpha($img, true);

    return $img;
}

/**
 * Dibuja el master centrado en un lienzo, escalado para entrar con `$margen` de aire
 * a cada lado (fracción del lado).
 *
 * `$fondo` en null deja el lienzo transparente; si no, es [r, g, b].
 *
 * @param  array{int, int, int}|null  $fondo
 */
function lienzo(GdImage $origen, int $ancho, int $alto, float $margen, ?array $fondo): GdImage
{
    $destino = imagecreatetruecolor($ancho, $alto);
    imagesavealpha($destino, true);

    if ($fondo === null) {
        // Sin blending: así `imagecopyresampled` escribe el alpha del origen tal cual
        // en vez de mezclarlo contra un fondo transparente, que ensucia los bordes.
        imagealphablending($destino, false);
        imagefilledrectangle($destino, 0, 0, $ancho, $alto, imagecolorallocatealpha($destino, 0, 0, 0, 127));
    } else {
        imagealphablending($destino, true);
        imagefilledrectangle($destino, 0, 0, $ancho, $alto, imagecolorallocate($destino, ...$fondo));
    }

    $anchoUtil = $ancho * (1 - 2 * $margen);
    $altoUtil = $alto * (1 - 2 * $margen);

    $origenAncho = imagesx($origen);
    $origenAlto = imagesy($origen);

    // Entra por el lado que primero toca el borde: el logo no se deforma nunca.
    $escala = min($anchoUtil / $origenAncho, $altoUtil / $origenAlto);

    $nuevoAncho = (int) round($origenAncho * $escala);
    $nuevoAlto = (int) round($origenAlto * $escala);

    imagecopyresampled(
        $destino,
        $origen,
        (int) round(($ancho - $nuevoAncho) / 2),
        (int) round(($alto - $nuevoAlto) / 2),
        0,
        0,
        $nuevoAncho,
        $nuevoAlto,
        $origenAncho,
        $origenAlto,
    );

    return $destino;
}

function guardar(GdImage $img, string $destino): void
{
    @mkdir(dirname($destino), 0755, true);
    imagepng($img, $destino, 9);
    imagedestroy($img);
}

/**
 * Un .ico con PNG adentro, que es lo que soportan todos los navegadores actuales
 * (y Windows desde Vista). GD no sabe escribir .ico, pero el contenedor es simple:
 * una cabecera de 6 bytes, una entrada de 16 por imagen y los PNG uno atrás del otro.
 *
 * Va con varios tamaños porque el favicon se usa a 16 px en la pestaña y a 32 o más
 * en los accesos directos del escritorio: dejar que el navegador reduzca el de 48
 * para la pestaña se ve peor que darle uno hecho a medida.
 *
 * @param  list<int>  $lados
 */
function ico(GdImage $origen, array $lados, string $destino): void
{
    $pngs = [];

    foreach ($lados as $lado) {
        $img = lienzo($origen, $lado, $lado, 0.06, [255, 255, 255]);

        ob_start();
        imagepng($img, null, 9);
        $pngs[$lado] = (string) ob_get_clean();

        imagedestroy($img);
    }

    $cantidad = count($pngs);
    $cabecera = pack('vvv', 0, 1, $cantidad);      // reservado, tipo 1 = ícono, cantidad
    $desplazamiento = 6 + 16 * $cantidad;

    $entradas = '';
    foreach ($pngs as $lado => $png) {
        $entradas .= pack(
            'CCCCvvVV',
            $lado >= 256 ? 0 : $lado,              // 0 significa 256
            $lado >= 256 ? 0 : $lado,
            0,                                     // colores de la paleta: 0 = truecolor
            0,                                     // reservado
            1,                                     // planos
            32,                                    // bits por pixel
            strlen($png),
            $desplazamiento,
        );

        $desplazamiento += strlen($png);
    }

    file_put_contents($destino, $cabecera.$entradas.implode('', $pngs));
}

$raiz = dirname(__DIR__);
$logo = master("{$raiz}/resources/img/logo.png");
$logotexto = master("{$raiz}/resources/img/logotexto.png");

$blanco = [255, 255, 255];

// 192 y 512 son los dos tamaños que Chrome exige para ofrecer instalar.
guardar(lienzo($logo, 192, 192, 0.08, $blanco), "{$raiz}/public/icons/icon-192.png");
guardar(lienzo($logo, 512, 512, 0.08, $blanco), "{$raiz}/public/icons/icon-512.png");

// El maskable lleva más aire: Android le recorta hasta un 20% de cada borde, y lo que
// queda afuera de la zona segura se pierde sin aviso.
guardar(lienzo($logo, 512, 512, 0.22, $blanco), "{$raiz}/public/icons/icon-512-maskable.png");

// iOS no usa el manifest para esto y no respeta la transparencia: la rellena de negro.
guardar(lienzo($logo, 180, 180, 0.06, $blanco), "{$raiz}/public/icons/apple-touch-icon.png");
// El `<link rel="apple-touch-icon">` apunta a la raíz, y iOS también lo busca ahí solo.
guardar(lienzo($logo, 180, 180, 0.06, $blanco), "{$raiz}/public/apple-touch-icon.png");

ico($logo, [16, 32, 48], "{$raiz}/public/favicon.ico");

// Los derivados para la interfaz: transparentes y livianos. El master de 832 px y
// 400 KB no tiene por qué viajar a un celular para mostrarse a 32 px.
guardar(lienzo($logo, 256, 256, 0, null), "{$raiz}/public/img/logo.png");

$anchoTexto = 512;
$altoTexto = (int) round($anchoTexto * imagesy($logotexto) / imagesx($logotexto));
guardar(lienzo($logotexto, $anchoTexto, $altoTexto, 0, null), "{$raiz}/public/img/logotexto.png");

foreach (['public/icons/*.png', 'public/img/*.png', 'public/apple-touch-icon.png', 'public/favicon.ico'] as $patron) {
    foreach (glob("{$raiz}/{$patron}") as $archivo) {
        $tamano = (array) @getimagesize($archivo);

        printf(
            "%-34s %5s x %-5s %8d bytes\n",
            str_replace("{$raiz}/", '', $archivo),
            $tamano[0] ?? '?',
            $tamano[1] ?? '?',
            filesize($archivo),
        );
    }
}
