<?php

namespace App\Filament\Commercial\Pages;

use App\Models\User;
use Filament\Pages\Page;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\Facades\Auth;

class DeclaracionesComercialDetalleHoy extends Page implements HasInfolists
{
    use InteractsWithInfolists;

    protected static ?string $navigationIcon = null;

    // No queremos que aparezca en el menú
    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.commercial.pages.declaraciones-comercial-detalle-hoy';

    public User $record;

    public function mount(User $record): void
    {
        $this->record = $record;
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->hasAnyRole([
            'team_leader',
            'sales_manager',
        ]) ?? false;
    }

    public static function getSlug(): string
    {
        // /gerente/declaraciones-comercial-detalle-hoy/{record}
        return 'declaraciones-comercial-detalle-hoy/{record}';
    }

    public function getTitle(): string
    {
        return 'Notas de hoy - ' . trim($this->record->name . ' ' . $this->record->last_name);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->record)
            ->schema([
                Tabs::make('Estados')
                    ->tabs([
                        Tabs\Tab::make('Oficina')->schema([
                            TextEntry::make('notas_oficina_hoy')
                                ->label('Notas en Oficina')
                                ->placeholder('No hay notas en OFICINA hoy.')
                                ->formatStateUsing(fn($state) => nl2br(e($state))) // respeta \n -> <br>
                                ->html(),
                        ]),

                        Tabs\Tab::make('Nulo')->schema([
                            TextEntry::make('notas_nulo_hoy')
                                ->label('Notas nulas')
                                ->placeholder('No hay notas NULO hoy.')
                                ->formatStateUsing(fn($state) => nl2br(e($state))) // respeta saltos de línea
                                ->html(),
                        ]),

                        Tabs\Tab::make('Ausente')->schema([
                            TextEntry::make('notas_ausente_hoy')
                                ->label('Notas ausentes')
                                ->placeholder('No hay notas AUSENTE hoy.')
                                ->formatStateUsing(fn($state) => nl2br(e($state))) // convierte \n en <br>
                                ->html(),
                        ]),

                        Tabs\Tab::make('Confirmado')->schema([
                            TextEntry::make('notas_confirmado_hoy')
                                ->label('Notas confirmadas')
                                ->placeholder('No hay notas CONFIRMADO hoy.')
                                ->formatStateUsing(fn($state) => nl2br(e($state))) // respeta saltos de línea
                                ->html(),
                        ]),

                        Tabs\Tab::make('Venta')->schema([
                            TextEntry::make('notas_venta_hoy')
                                ->label('Ventas')
                                ->placeholder('No hay notas VENTA hoy.')
                                ->formatStateUsing(fn($state) => nl2br(e($state)))
                                ->html(),
                        ]),
                    ]),
            ]);
    }
}
