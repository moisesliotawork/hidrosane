<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\MaxWidth;
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
use App\Filament\SuperAdmin\Pages\ContratosPorMes;
use App\Filament\SuperAdmin\Pages\RecuperarContratoImagen;
use App\Filament\SuperAdmin\Pages\ReengancharDocumentosHuerfanos;
use App\Filament\SuperAdmin\Pages\ViewProfile;
use Illuminate\Support\Carbon;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use App\Filament\Widgets\SalesAndDeliveriesStats;
use Filament\View\PanelsRenderHook;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\View\TablesRenderHook;
use App\Filament\SuperAdmin\Resources\VentaResource\Pages\ListVentas as SuperAdminListVentas;
use Illuminate\Support\Facades\Blade;

class SuperAdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_START,
            fn (): string => view('filament.admin.resources.venta-resource.nro-contrato-toolbar-search')->render(),
            scopes: [
                RecuperarContratoImagen::class,
                SuperAdminListVentas::class,
            ],
        );

        return $panel
            ->id('superAdmin')
            ->path('superAdmin')
            ->login()
            ->favicon(asset('favicon.ico'))
            ->brandName(\App\Support\Brand::name())
            ->brandLogo(fn() => view('filament.brand.logo'))
            ->userMenuItems([
                'profile' => MenuItem::make()
                    ->label('Mi Perfil')
                    ->url(fn(): string => ViewProfile::getUrl())
                    ->icon('heroicon-o-user'),
            ])
            ->colors([
                'primary' => \App\Support\Brand::primary(),
            ])
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn(): string => Blade::render(<<<'BLADE'
                    <style>
                        /* Filas más compactas en todas las tablas de recursos de SuperAdmin */
                        .fi-panel-superAdmin .fi-ta-header-cell {
                            padding-top: 0.5rem !important;
                            padding-bottom: 0.5rem !important;
                        }
                        .fi-panel-superAdmin .fi-ta-cell .py-4 {
                            padding-top: 0.25rem !important;
                            padding-bottom: 0.25rem !important;
                        }

                        /* Parpadeo en los badges rojos (contadores en 1+) de la navegación */
                        .fi-panel-superAdmin .fi-sidebar-nav .fi-badge.fi-color-danger {
                            animation: oh-badge-blink 1s ease-in-out infinite;
                        }
                        @keyframes oh-badge-blink {
                            0%, 100% { opacity: 1; }
                            50% { opacity: 0.35; }
                        }
                    </style>
                BLADE)
            )
            ->renderHook(
                PanelsRenderHook::PAGE_HEADER_ACTIONS_BEFORE,
                function (): string {
                    $actualKey = Carbon::now()->format('Y-m');
                    $anteriorKey = Carbon::now()->subMonthNoOverflow()->format('Y-m');

                    return Blade::render(<<<'BLADE'
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                            <div class="flex items-center gap-x-2">
                                <x-heroicon-o-calendar-days class="h-5 w-5 text-primary-600 dark:text-primary-400 shrink-0" />
                                <span class="text-sm font-medium text-primary-600 dark:text-primary-400 whitespace-nowrap">
                                    Contratos/Mes/Actual
                                </span>
                                <x-filament::badge color="info">
                                    {{ $actual }}
                                </x-filament::badge>
                            </div>

                            <div class="flex items-center gap-x-2">
                                <x-heroicon-o-calendar-days class="h-5 w-5 text-primary-600 dark:text-primary-400 shrink-0" />
                                <span class="text-sm font-medium text-primary-600 dark:text-primary-400 whitespace-nowrap">
                                    Contratos/Mes/Anterior
                                </span>
                                <x-filament::badge color="info">
                                    {{ $anterior }}
                                </x-filament::badge>
                            </div>
                        </div>
                    BLADE, [
                        'actual' => ContratosPorMes::contratosDelMes($actualKey),
                        'anterior' => ContratosPorMes::contratosDelMes($anteriorKey),
                    ]);
                },
                scopes: ContratosPorMes::class,
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn(): string => Blade::render('@if(auth()->check()) 
                <div class="flex items-center justify-end gap-2 mr-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                    {{ auth()->user()->empleado_id . " - " . auth()->user()->name . " " . auth()->user()->last_name }}
                </div>
            @endif')
            )
            ->renderHook(
                PanelsRenderHook::BODY_START,
                function (): string {
                    if (! auth()->check()) {
                        return '';
                    }

                    return Blade::render(<<<'BLADE'
                        <div>
                            @livewire('super-admin.contratos-mes-alert')
                            @livewire('super-admin.ver-datos-contrato-search')
                        </div>
                    BLADE);
                },
            )
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('16rem')
            ->maxContentWidth(MaxWidth::Full)
            ->navigationGroups([
                NavigationGroup::make('RECUPERACION CONTRATOS'),
                NavigationGroup::make('Registros'),
                NavigationGroup::make('Asignación de Notas')
                    ->collapsed(),
                NavigationGroup::make('OTROS'),
                NavigationGroup::make('General'),
            ])
            ->discoverResources(in: app_path('Filament/SuperAdmin/Resources'), for: 'App\\Filament\\SuperAdmin\\Resources')
            ->discoverPages(in: app_path('Filament/SuperAdmin/Pages'), for: 'App\\Filament\\SuperAdmin\\Pages')
            ->pages([
                RecuperarContratoImagen::class,
                ReengancharDocumentosHuerfanos::class,
            ])
            ->discoverWidgets(in: app_path('Filament/SuperAdmin/Widgets'), for: 'App\\Filament\\SuperAdmin\\Widgets')
            ->widgets([
                SalesAndDeliveriesStats::class,
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
