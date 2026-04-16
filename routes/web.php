<?php

use App\Http\Controllers\EventoInscripcionController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])
    ->withoutMiddleware([VerifyCsrfToken::class]);

Route::get('/eventos/{eventoFecha}/inscripcion', [EventoInscripcionController::class, 'create'])
    ->name('eventos.inscripcion.create');
Route::post('/eventos/{eventoFecha}/inscripcion', [EventoInscripcionController::class, 'store'])
    ->name('eventos.inscripcion.store');
