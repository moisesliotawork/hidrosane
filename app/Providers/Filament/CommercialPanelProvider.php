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
use App\Filament\Widgets\ActiveWorkSessionWidget;
use App\Http\Middleware\StartWorkSession;
use App\Filament\Commercial\Pages\ViewProfile;
use App\Filament\Commercial\Pages\Notas2;
use App\Filament\Commercial\Pages\SeguimientoDeRuta;
use App\Filament\Commercial\Resources\NoteResource;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationItem;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

class CommercialPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('comercial')
            ->path('comercial')
            ->favicon(asset('favicon.ico'))
            ->brandLogo(fn() => view('filament.brand.logo'))
            ->login()
            ->databaseNotifications()
            ->userMenuItems([
                'profile' => MenuItem::make()
                    ->label(fn(): string => auth()->user()?->display_name ?? 'Mi Perfil')
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
            ->discoverResources(in: app_path('Filament/Commercial/Resources'), for: 'App\\Filament\\Commercial\\Resources')
            ->discoverPages(in: app_path('Filament/Commercial/Pages'), for: 'App\\Filament\\Commercial\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Commercial/Widgets'), for: 'App\\Filament\\Commercial\\Widgets')
            ->navigationItems([
                NavigationItem::make('NOTAS')
                    ->icon('heroicon-o-document-text')
                    ->url(fn (): string => Notas2::getUrl())
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.comercial.pages.notas'))
                    ->sort(-1),
                NavigationItem::make('Notas (listado)')
                    ->icon('heroicon-o-rectangle-stack')
                    ->url(fn (): string => NoteResource::getUrl('index'))
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.comercial.resources.notes.*'))
                    ->sort(0),
                NavigationItem::make('Seguimiento de ruta')
                    ->icon('heroicon-o-document-text')
                    ->url(fn (): string => SeguimientoDeRuta::getUrl())
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.comercial.pages.seguimiento-de-ruta'))
                    ->visible(fn (): bool => auth()->user()?->hasAnyRole(['team_leader', 'sales_manager']) ?? false)
                    ->sort(1),
            ])
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
