<?php

namespace App\Sondas;

use App\Enums\TipoChequeo;
use App\Models\Proyecto;

/**
 * Una forma de mirar un sitio.
 *
 * Se llama `Sonda` y no `Chequeo` a propósito: `Chequeo` es el **modelo** del
 * resultado guardado en la base. Son dos cosas distintas y darles el mismo
 * nombre garantiza una confusión.
 *
 * Cada sonda decide sola a qué proyectos aplica (`aplicaA`). Eso es lo que evita
 * el `if` gigante en el ejecutor y lo que impide preguntarle a un sitio estático
 * por las cabeceras del XHR de Inertia, que daría una falla sin significado.
 */
interface Sonda
{
    public function tipo(): TipoChequeo;

    public function aplicaA(Proyecto $proyecto): bool;

    public function ejecutar(Proyecto $proyecto): Resultado;
}
