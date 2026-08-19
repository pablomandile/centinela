<?php

/**
 * Los íconos de Centinela: un ojo abierto sobre fondo oscuro.
 *
 * Se dibuja a 4× y se reduce con `imagecopyresampled`. GD no antialiasea arcos ni
 * elipses —`imageantialias()` solo aplica a líneas y polígonos—, así que sin el
 * supersampling los bordes salen dentados y se ve en el ícono de 192.
 */
function icono(int $lado, float $margen, string $destino): void
{
    $escala = 4;
    $grande = $lado * $escala;

    $img = imagecreatetruecolor($grande, $grande);
    imagealphablending($img, true);
    imagesavealpha($img, true);

    $fondo = imagecolorallocate($img, 13, 13, 15);   // #0d0d0f
    $claro = imagecolorallocate($img, 250, 250, 250);
    $verde = imagecolorallocate($img, 16, 185, 129); // el verde del semáforo "ok"

    imagefilledrectangle($img, 0, 0, $grande, $grande, $fondo);

    $centro = $grande / 2;
    // El margen deja la zona segura que Android recorta en los íconos maskable.
    $radio = $centro * (1 - $margen);

    // La lente del ojo: una elipse rellena de claro y otra más chica de fondo
    // adentro. Sale más limpio que un arco con grosor.
    $anchoOjo = (int) round($radio * 2);
    $altoOjo = (int) round($radio * 1.2);
    $borde = (int) round($radio * 0.13);

    imagefilledellipse($img, (int) $centro, (int) $centro, $anchoOjo, $altoOjo, $claro);
    imagefilledellipse($img, (int) $centro, (int) $centro, $anchoOjo - $borde * 2, $altoOjo - $borde * 2, $fondo);

    // El iris, relleno, con la pupila oscura adentro.
    $iris = (int) round($radio * 0.62);
    imagefilledellipse($img, (int) $centro, (int) $centro, $iris, $iris, $claro);

    $pupila = (int) round($radio * 0.26);
    imagefilledellipse($img, (int) $centro, (int) $centro, $pupila, $pupila, $fondo);

    // Un destello verde sobre el iris: el semáforo del tablero.
    $destello = (int) round($radio * 0.15);
    imagefilledellipse(
        $img,
        (int) round($centro + $radio * 0.17),
        (int) round($centro - $radio * 0.15),
        $destello,
        $destello,
        $verde,
    );

    $final = imagecreatetruecolor($lado, $lado);
    imagealphablending($final, true);
    imagesavealpha($final, true);
    imagecopyresampled($final, $img, 0, 0, 0, 0, $lado, $lado, $grande, $grande);

    imagepng($final, $destino, 9);
    imagedestroy($img);
    imagedestroy($final);
}

$dir = $argv[1];
@mkdir($dir, 0755, true);

// 192 y 512 son los dos tamaños que Chrome exige para ofrecer instalar.
icono(192, 0.16, "{$dir}/icon-192.png");
icono(512, 0.16, "{$dir}/icon-512.png");
// El maskable lleva más margen: Android le recorta hasta un 20% de cada borde.
icono(512, 0.28, "{$dir}/icon-512-maskable.png");
// iOS no usa el manifest para esto.
icono(180, 0.12, "{$dir}/apple-touch-icon.png");

foreach (glob("{$dir}/*.png") as $archivo) {
    printf("%-28s %s\n", basename($archivo), filesize($archivo).' bytes');
}
