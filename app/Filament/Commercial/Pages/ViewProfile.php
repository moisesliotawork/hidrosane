<?php

namespace App\Filament\Commercial\Pages;

use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class ViewProfile extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-user';
    protected static string $view = 'filament.commercial.pages.view-profile';
    protected static ?string $navigationLabel = 'Mi Perfil';
    protected static ?string $title = 'Mi Perfil';

    public function mount(): void
    {
        // No necesitamos lógica especial en mount para infolist
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $user = Auth::user();

        return $infolist
            ->record($user)
            ->schema([
                Section::make('Información Personal')
                    ->schema([
                        TextEntry::make('empleado_id')
                            ->label('ID Empleado'),

                        TextEntry::make('name')
                            ->label('Nombre'),

                        TextEntry::make('last_name')
                            ->label('Apellido'),

                        TextEntry::make('roles.name')
                            ->label('Rol')
                            ->formatStateUsing(function ($state) {
                                $roleNames = [
                                    'admin' => 'ADMINISTRADOR',
                                    'head_of_room' => 'JEFE DE SALA',
                                    'teleoperator' => 'TELEOPERADOR',
                                    'commercial' => 'COMERCIAL'
                                ];

                                return $roleNames[strtolower($state)] ?? 'Sin rol asignado';
                            }),
                    ])
                    ->columns(2),
            ]);
    }
}