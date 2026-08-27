<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use App\Observers\VentaOfertaObserver;


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
        // Evitar indexación en buscadores
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn(): string => Blade::render('<meta name="robots" content="noindex, nofollow">'),
        );

        \App\Models\Venta::observe(\App\Observers\VentaObserver::class);
        // \App\Models\VentaOferta::observe(VentaOfertaObserver::class);

        //Personalizar colores de filament
        FilamentColor::register([
            'danger' => Color::Red,
            'gray' => Color::Zinc,
            'info' => Color::Blue,
            'primary' => Color::Sky,
            'success' => Color::Green,
            'warning' => Color::Amber,
            'orange' => Color::Orange,
            'yellow' => Color::Yellow,
            'pink' => Color::Pink,
            'purple' => Color::Purple,    // NO SALE A CALLE
            'teal' => Color::Teal,
            'gray_light' => Color::hex('#737373'),
        ]);

        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn (): string => view('filament.hooks.venta-document-upload-confirm')->render(),
        );
    }

}
