<?php

namespace App\Http\Middleware;

use App\Filament\Pages\AsistenciaGruposCrecimiento;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictFacilitadorPanelAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole('facilitador') || $user->hasRole('admin')) {
            return $next($request);
        }

        if ($request->is('livewire/*') || $request->is('admin/livewire/*')) {
            return $next($request);
        }

        $routeName = (string) ($request->route()?->getName() ?? '');

        if ($routeName === 'filament.admin.pages.dashboard') {
            return redirect(AsistenciaGruposCrecimiento::getUrl());
        }

        if (in_array($routeName, [
            'filament.admin.pages.asistencia-grupos-crecimiento',
            'filament.admin.auth.logout',
        ], true)) {
            return $next($request);
        }

        abort(403);
    }
}
