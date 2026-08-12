<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class EnergiaPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->id('energia')->path('energia')->login()
            ->brandName('Energia')->colors(['primary' => Color::Amber])
            ->discoverResources(in: app_path('Filament/Energia/Resources'), for: 'App\\Filament\\Energia\\Resources')
            ->discoverPages(in: app_path('Filament/Energia/Pages'), for: 'App\\Filament\\Energia\\Pages')
            ->widgets([AccountWidget::class])
            ->middleware([EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class,
                AuthenticateSession::class, ShareErrorsFromSession::class, VerifyCsrfToken::class,
                SubstituteBindings::class, DisableBladeIconComponents::class, DispatchServingFilamentEvent::class])
            ->authMiddleware([Authenticate::class]);
    }
}
