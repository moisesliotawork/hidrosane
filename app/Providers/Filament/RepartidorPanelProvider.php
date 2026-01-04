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
use App\Filament\Repartidor\Pages\ViewProfile;
use Filament\Navigation\MenuItem;
use App\Filament\Widgets\RepartidorStats;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

class RepartidorPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('repartidor')
            ->path('repartidor')
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
            ->renderHook(

                PanelsRenderHook::USER_MENU_BEFORE,
                fn(): string => Blade::render('@if(auth()->check()) 
                <div class="flex items-center justify-end gap-2 mr-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                    {{ auth()->user()->empleado_id . " - " . auth()->user()->name . " " . auth()->user()->last_name }}
                </div>
            @endif')
            )
            ->discoverResources(in: app_path('Filament/Repartidor/Resources'), for: 'App\\Filament\\Repartidor\\Resources')
            ->discoverPages(in: app_path('Filament/Repartidor/Pages'), for: 'App\\Filament\\Repartidor\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Repartidor/Widgets'), for: 'App\\Filament\\Repartidor\\Widgets')
            ->widgets([
                RepartidorStats::class,
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
