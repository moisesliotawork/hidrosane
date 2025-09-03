<?php

namespace App\Filament\Gerente\Pages;

use App\Models\User;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;

class ComercialesVerNotas extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Notas Gerente';
    protected static ?string $title = 'Notas Gerente';
    protected static ?string $slug = 'comerciales-ver-notas';
    protected static ?int $navigationSort = 10;

    // Usa un blade simple que renderiza $this->table
    protected static string $view = 'filament.gerente.pages.comerciales-ver-notas';

    public function table(Table $table): Table
    {
        return $table
            // 👉 Usuarios que tengan el rol "comercial" o "team_leader"
            // Si tus nombres de rol difieren, cámbialos aquí.
            ->query(
                User::query()->role(['commercial', 'team_leader'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('empleado_id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->formatStateUsing(fn($record) => trim($record->name . ' ' . ($record->last_name ?? '')))
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_notas')
                    ->label('Ver notas')
                    ->button()
                    ->outlined()
                    ->color('primary')
                    // Por ahora no hace nada
                    ->action(fn() => null),
            ])
            ->striped()
            ->paginated(true)
            ->defaultPaginationPageOption(25)
            ->defaultSort('name');
    }
}
