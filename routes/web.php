<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\ProyectoController;
use App\Http\Controllers\SaludController;
use App\Http\Controllers\TableroController;
use Illuminate\Support\Facades\Route;

/*
 * Centinela no tiene landing: es una herramienta con login. La raíz manda al
 * tablero, y el middleware `auth` de ahí desvía al login a quien no entró.
 */
Route::redirect('/', '/dashboard')->name('home');

/*
 * Lo único público. Es para el monitor externo: Centinela no puede avisar que se
 * cayó estando caído.
 */
Route::get('salud', SaludController::class)->name('salud');

/*
 * Ingreso con Google. Las rutas van en inglés y bajo /auth como el resto de las
 * de autenticación, porque el redirect ya está registrado así en Google Cloud y
 * cambiarlo obliga a editarlo allá con el riesgo de un redirect_uri_mismatch.
 *
 * Sin credenciales configuradas devuelven 404 (ver GoogleController).
 */
Route::middleware('guest')->group(function () {
    Route::get('auth/google/redirect', [GoogleController::class, 'redirigir'])
        ->name('google.redirect');

    Route::get('auth/google/callback', [GoogleController::class, 'volver'])
        ->name('google.callback');
});

Route::middleware(['auth'])->group(function () {
    // Sin `verified`: la verificación de email es una feature de Fortify que está
    // apagada y quien entra por Google llega con el email ya verificado.
    Route::get('dashboard', TableroController::class)->name('dashboard');

    Route::get('proyectos', [ProyectoController::class, 'index'])->name('proyectos.index');
    Route::post('proyectos', [ProyectoController::class, 'store'])->name('proyectos.store');

    /*
     * El detalle va después del alta para que `proyectos` no se coma como slug la
     * palabra que usa otra ruta. Con `{proyecto}` resolviendo por slug, el orden
     * importa.
     */
    Route::get('proyectos/{proyecto}', [ProyectoController::class, 'show'])->name('proyectos.show');
    Route::put('proyectos/{proyecto}', [ProyectoController::class, 'update'])->name('proyectos.update');
    Route::delete('proyectos/{proyecto}', [ProyectoController::class, 'destroy'])->name('proyectos.destroy');
    Route::post('proyectos/{proyecto}/detectar', [ProyectoController::class, 'detectar'])->name('proyectos.detectar');

    // El buscador global sobre todos los documentos.
    Route::get('documentos', [DocumentoController::class, 'index'])->name('documentos.index');

    /*
     * Documentos, anidados en su proyecto.
     *
     * `scopeBindings()` no es decorativo: los slugs de documento son únicos **por
     * proyecto**, así que dos proyectos pueden tener los dos un "readme". Sin el
     * scope, `{documento}` resolvería el primero que encuentre en toda la tabla y
     * mostraría el documento de otro proyecto. Con él, se busca dentro de
     * `$proyecto->documentos()` y un par que no corresponde da 404 solo.
     */
    Route::scopeBindings()->group(function () {
        Route::post('proyectos/{proyecto}/documentos', [DocumentoController::class, 'store'])
            ->name('documentos.store');

        Route::get('proyectos/{proyecto}/documentos/{documento}', [DocumentoController::class, 'show'])
            ->name('documentos.show');

        Route::get('proyectos/{proyecto}/documentos/{documento}/descargar', [DocumentoController::class, 'descargar'])
            ->name('documentos.descargar');

        Route::get('proyectos/{proyecto}/documentos/{documento}/pdf', [PdfController::class, 'documento'])
            ->name('documentos.pdf');

        Route::delete('proyectos/{proyecto}/documentos/{documento}', [DocumentoController::class, 'destroy'])
            ->name('documentos.destroy');
    });

    Route::get('proyectos/{proyecto}/dossier', [PdfController::class, 'dossier'])->name('proyectos.dossier');
});

require __DIR__.'/settings.php';
