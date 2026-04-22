<?php

namespace App\Http\Middleware;

use App\Filament\Pages\AsistenciasPendientes;
use App\Filament\Pages\IpnDashboard;
use App\Filament\Pages\MisGruposMinisteriales;
use App\Filament\Pages\MisMetagrupos;
use App\Filament\Pages\ResumenAsistenciaGrupos;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictFacilitadorPanelAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->hasRole('admin')) {
            return $next($request);
        }

        if ($request->is('livewire/*') || $request->is('admin/livewire/*')) {
            return $next($request);
        }

        $routeName = (string) ($request->route()?->getName() ?? '');
        $isFacilitador = $user->hasRole('facilitador');
        $isLider = $user->hasRole('lider');
        $isCoordinadorGrupos = $user->hasRole('coordinador_grupos');
        $isIpn = $user->hasRole(['director_ipn', 'servidor_ipn']);

        if ($routeName === 'filament.admin.pages.dashboard') {
            if ($user->hasCombinedFacilitadorLiderAccess() || $isCoordinadorGrupos) {
                return $next($request);
            }

            if ($isCoordinadorGrupos) {
                return redirect(AsistenciasPendientes::getUrl());
            }

            if ($isFacilitador) {
                return redirect(ResumenAsistenciaGrupos::getUrl());
            }

            if ($isLider) {
                return redirect(
                    $user->metagruposLiderados()->exists()
                        ? MisMetagrupos::getUrl()
                        : MisGruposMinisteriales::getUrl()
                );
            }

            if ($isIpn) {
                return redirect(IpnDashboard::getUrl());
            }
        }

        if ($isFacilitador || $isLider || $isCoordinadorGrupos || $isIpn) {
            $allowedRoutes = [
                'filament.admin.auth.logout',
            ];

            if ($isFacilitador) {
                $allowedRoutes = array_merge($allowedRoutes, [
                    'filament.admin.pages.asistencia-grupos-crecimiento',
                    'filament.admin.pages.resumen-asistencia-grupos',
                ]);
            }

            if ($isCoordinadorGrupos) {
                $allowedRoutes = array_merge($allowedRoutes, [
                    'filament.admin.pages.dashboard',
                    'filament.admin.pages.asistencia-grupos-crecimiento',
                    'filament.admin.pages.resumen-asistencia-grupos',
                    'filament.admin.pages.asistencias-pendientes',
                    'filament.admin.resources.grupos.index',
                    'filament.admin.resources.grupos.create',
                    'filament.admin.resources.grupos.edit',
                    'filament.admin.resources.grupos.participacion',
                ]);
            }

            if ($isLider) {
                $allowedRoutes = array_merge($allowedRoutes, [
                    'filament.admin.pages.mis-metagrupos',
                    'filament.admin.pages.mis-grupos-ministeriales',
                    'filament.admin.pages.resumen-grupo-ministerial',
                    'filament.admin.pages.resumen-asistencia-grupos',
                    'filament.admin.resources.metagrupos.view',
                ]);
            }

            if ($isIpn) {
                $allowedRoutes = array_merge($allowedRoutes, [
                    'filament.admin.pages.ipn-dashboard',
                    'filament.admin.pages.ipn-tomar-asistencia',
                    'filament.admin.pages.ipn-reporte-asistencia',
                    'filament.admin.resources.ipn-ninos.index',
                    'filament.admin.resources.ipn-ninos.create',
                    'filament.admin.resources.ipn-ninos.edit',
                    'filament.admin.resources.ipn-aulas.index',
                    'filament.admin.resources.ipn-aulas.create',
                    'filament.admin.resources.ipn-aulas.edit',
                ]);
            }

            if (in_array($routeName, $allowedRoutes, true)) {
                return $next($request);
            }

            abort(403);
        }

        return $next($request);
    }
}
