<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Http\Middleware\StartWorkSession;
use App\Filament\Widgets\ActiveWorkSessionWidget;
use App\Filament\HeadOfRoom\Pages\ViewProfile;
use Filament\Navigation\MenuItem;

class HeadOfRoomPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('jefe-sala')
            ->path('jefe-sala')
            ->login()
            ->favicon(asset('favicon.ico'))
            ->brandLogo(fn() => view('filament.brand.logo'))
            ->userMenuItems([
                'profile' => MenuItem::make()
                    ->label('Mi Perfil')
                    ->url(fn(): string => ViewProfile::getUrl())
                    ->icon('heroicon-o-user'),
            ])
            ->colors([
                'primary' => Color::Lime,
            ])
            ->topNavigation()
            ->discoverResources(in: app_path('Filament/HeadOfRoom/Resources'), for: 'App\\Filament\\HeadOfRoom\\Resources')
            ->discoverPages(in: app_path('Filament/HeadOfRoom/Pages'), for: 'App\\Filament\\HeadOfRoom\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/HeadOfRoom/Widgets'), for: 'App\\Filament\\HeadOfRoom\\Widgets')
            ->widgets([
                ActiveWorkSessionWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                StartWorkSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                \App\Http\Middleware\RedirectPanelLoginToAdmin::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
