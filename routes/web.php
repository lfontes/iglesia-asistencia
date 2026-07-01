<?php

use App\Http\Controllers\EventoInscripcionController;
use App\Http\Controllers\ReporteGruposCrecimientoPdfController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/images/logo-iglesia-libres.png', function () {
    $path = public_path('images/logo-iglesia-libres.png');

    abort_unless(is_file($path), 404);

    return response()->file($path, [
        'Cache-Control' => 'public, max-age=604800',
    ]);
})->name('assets.logo');

Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])
    ->withoutMiddleware([VerifyCsrfToken::class]);

Route::get('/admin/reporte-grupos-crecimiento/pdf', ReporteGruposCrecimientoPdfController::class)
    ->middleware(['web', 'auth'])
    ->name('reporte-grupos-crecimiento.pdf');

Route::get('/eventos/{eventoFecha}/inscripcion', [EventoInscripcionController::class, 'create'])
    ->name('eventos.inscripcion.create');
Route::post('/eventos/{eventoFecha}/inscripcion', [EventoInscripcionController::class, 'store'])
    ->name('eventos.inscripcion.store');
Route::post('/eventos/{eventoFecha}/inscripcion/cancelar/buscar', [EventoInscripcionController::class, 'cancelarBuscar'])
    ->name('eventos.inscripcion.cancelar.buscar');
Route::post('/eventos/{eventoFecha}/inscripcion/cancelar', [EventoInscripcionController::class, 'cancelar'])
    ->name('eventos.inscripcion.cancelar');
