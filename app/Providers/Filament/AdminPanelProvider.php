<?php

namespace App\Providers\Filament;

use App\Filament\Pages\AsistenciaGruposCrecimiento;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\AsistenciasSemanalesGruposWidget;
use App\Filament\Widgets\ResumenGeneralWidget;
use App\Http\Middleware\RestrictFacilitadorPanelAccess;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->homeUrl(function (): string {
                $user = auth()->user();

                if ($user && $user->hasRole('facilitador') && ! $user->hasRole('admin')) {
                    return AsistenciaGruposCrecimiento::getUrl();
                }

                return Dashboard::getUrl();
            })
            ->brandName('Iglesia de los Libres')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                ResumenGeneralWidget::class,
                AsistenciasSemanalesGruposWidget::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                function (): HtmlString {
                    $assetPath = 'css/filament/admin-overrides.css';
                    $fullPath = public_path($assetPath);
                    $version = is_file($fullPath) ? filemtime($fullPath) : null;
                    $href = asset($assetPath).($version ? "?v={$version}" : '');

                    return new HtmlString('<link rel="stylesheet" href="'.$href.'">');
                }
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                RestrictFacilitadorPanelAccess::class,
            ]);
    }
}
