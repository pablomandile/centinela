<?php

namespace App\Http\Controllers;

use App\Models\Chequeo;
use App\Models\Incidente;
use App\Models\Proyecto;
use Illuminate\Http\JsonResponse;

/**
 * El único endpoint público de Centinela.
 *
 * Centinela está cargado como proyecto y se audita a sí mismo, pero **no puede
 * avisar su propia caída**: el aviso lo dispara el cron, y si esto se cayó del todo
 * el cron tampoco corre. Un monitor externo gratuito (healthchecks.io, UptimeRobot)
 * pega acá y avisa si deja de contestar.
 *
 * No expone nada sensible: cuántos proyectos hay, cuántos incidentes abiertos y
 * cuándo fue el último chequeo. Ni nombres, ni URLs, ni mensajes.
 */
class SaludController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $ultimo = Chequeo::max('ejecutado_at');

        return response()->json([
            'ok' => true,
            'proyectos' => Proyecto::activos()->count(),
            'incidentes_abiertos' => Incidente::abiertos()->count(),
            'ultimo_chequeo' => $ultimo,
            /*
             * El dato que de verdad importa del monitor externo: si el scheduler se
             * murió, esto se estira y el sitio sigue contestando 200. Un monitor
             * que solo mire el código no lo vería.
             */
            'minutos_desde_el_ultimo_chequeo' => $ultimo === null
                ? null
                : (int) round(now()->diffInMinutes($ultimo, absolute: true)),
        ]);
    }
}
