<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;
use App\Models\Persona;
use App\Models\Grupo;
use App\Models\Evento;
use App\Models\EventoFecha;
use App\Models\EventoInscripcion;
use App\Models\User;
use App\Models\ParticipacionGrupo;
use App\Models\WhatsAppMessage;
use App\Models\Asistencia;
use App\Models\AsistenciaGrupo;
use App\Observers\PersonaObserver;
use App\Observers\GrupoObserver;
use App\Observers\EventoObserver;
use App\Observers\EventoFechaObserver;
use App\Observers\EventoInscripcionObserver;
use App\Observers\UserObserver;
use App\Observers\ParticipacionGrupoObserver;
use App\Observers\WhatsAppMessageObserver;
use App\Observers\AsistenciaObserver;
use App\Observers\AsistenciaGrupoObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        app()->setLocale('es');
        Carbon::setLocale('es');
        Date::setLocale('es');

        // Register model observers for activity logging
        Persona::observe(PersonaObserver::class);
        Grupo::observe(GrupoObserver::class);
        Evento::observe(EventoObserver::class);
        EventoFecha::observe(EventoFechaObserver::class);
        EventoInscripcion::observe(EventoInscripcionObserver::class);
        User::observe(UserObserver::class);
        ParticipacionGrupo::observe(ParticipacionGrupoObserver::class);
        WhatsAppMessage::observe(WhatsAppMessageObserver::class);
        Asistencia::observe(AsistenciaObserver::class);
        AsistenciaGrupo::observe(AsistenciaGrupoObserver::class);
    }
}
