<?php

namespace App\Filament\Commercial\Pages;

use App\Models\User;
use Filament\Pages\Page;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\TextEntry;

class DeclaracionesComercialDetalleAyer extends Page implements HasInfolists
{
    use InteractsWithInfolists;

    protected static ?string $navigationIcon = null;

    // No queremos que aparezca en el menú
    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.commercial.pages.declaraciones-comercial-detalle-ayer';

    public User $record;

    public function mount(User $record): void
    {
        $this->record = $record;
    }

    public static function getSlug(): string
    {
        // /gerente/declaraciones-comercial-detalle-ayer/{record}
        return 'declaraciones-comercial-detalle-ayer/{record}';
    }

    public function getTitle(): string
    {
        return 'Notas de ayer - ' . trim($this->record->name . ' ' . $this->record->last_name);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->record)
            ->schema([
                Tabs::make('Estados')
                    ->tabs([
                        Tabs\Tab::make('Oficina')->schema([
                            TextEntry::make('notas_oficina_ayer')
                                ->label('Notas en Oficina')
                                ->placeholder('No hay notas en OFICINA ayer.')
                                ->formatStateUsing(fn($state) => nl2br(e($state)))
                                ->html(),
                        ]),

                        Tabs\Tab::make('Nulo')->schema([
                            TextEntry::make('notas_nulo_ayer')
                                ->label('Notas nulas')
                                ->placeholder('No hay notas NULO ayer.')
                                ->formatStateUsing(fn($state) => nl2br(e($state)))
                                ->html(),
                        ]),

                        Tabs\Tab::make('Ausente')->schema([
                            TextEntry::make('notas_ausente_ayer')
                                ->label('Notas ausentes')
                                ->placeholder('No hay notas AUSENTE ayer.')
                                ->formatStateUsing(fn($state) => nl2br(e($state)))
                                ->html(),
                        ]),

                        Tabs\Tab::make('Confirmado')->schema([
                            TextEntry::make('notas_confirmado_ayer')
                                ->label('Notas confirmadas')
                                ->placeholder('No hay notas CONFIRMADO ayer.')
                                ->formatStateUsing(fn($state) => nl2br(e($state)))
                                ->html(),
                        ]),

                        Tabs\Tab::make('Venta')->schema([
                            TextEntry::make('notas_venta_ayer')
                                ->label('Ventas')
                                ->placeholder('No hay notas VENTA ayer.')
                                ->formatStateUsing(fn($state) => nl2br(e($state)))
                                ->html(),
                        ]),
                    ]),
            ]);
    }
}
