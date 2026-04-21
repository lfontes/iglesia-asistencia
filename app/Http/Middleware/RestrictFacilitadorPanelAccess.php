<?php

namespace App\Http\Middleware;

use App\Filament\Pages\AsistenciaGruposCrecimiento;
use App\Filament\Pages\IpnDashboard;
use App\Filament\Pages\MisGruposMinisteriales;
use App\Filament\Pages\MisMetagrupos;
use App\Filament\Pages\ResumenAsistenciaGrupos;
use App\Filament\Pages\ResumenGrupoMinisterial;
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
        $isIpn = $user->hasRole(['director_ipn', 'servidor_ipn']);

        if ($routeName === 'filament.admin.pages.dashboard') {
            if ($user->hasCombinedFacilitadorLiderAccess()) {
                return $next($request);
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

        if ($isFacilitador || $isLider || $isIpn) {
            $allowedRoutes = [
                'filament.admin.auth.logout',
            ];

            if ($isFacilitador) {
                $allowedRoutes = array_merge($allowedRoutes, [
                    'filament.admin.pages.asistencia-grupos-crecimiento',
                    'filament.admin.pages.resumen-asistencia-grupos',
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
