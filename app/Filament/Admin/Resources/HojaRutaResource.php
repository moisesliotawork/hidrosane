<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\HojaRutaResource\Pages;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;

class HojaRutaResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Hoja de Ruta';
    protected static ?string $modelLabel = 'Hoja de Ruta';
    protected static ?string $slug = 'hoja-rutas';
    protected static ?int $navigationSort = 12;

    // 🔓 Evitar bloqueos por políticas en esta vista (solo lectura)
    public static function canViewAny(): bool
    {
        return true;
    }
    public static function canView(mixed $record): bool
    {
        return true;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('empleado_id')->label('Empleado ID')->sortable()->searchable(),
                TextColumn::make('full_name')
                    ->label('Nombre completo')
                    ->state(fn(User $r) => trim($r->name . ' ' . $r->last_name))
                    ->sortable()->searchable(['name', 'last_name']),
                TextColumn::make('dni')->label('DNI')->sortable()->searchable(),
                TextColumn::make('phone')->label('Teléfono')->searchable(),
            ])
            ->actions([
                Tables\Actions\Action::make('hojaRuta')
                    ->label('Ver Hoja Ruta')
                    ->icon('heroicon-o-map')
                    ->url(fn(User $r) => Pages\HojaRuta::getUrl(['record' => $r->getKey()])),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->role('commercial')
            ->select(['id', 'empleado_id', 'name', 'last_name', 'dni', 'phone']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHojaRuta::route('/'),
            'hoja-ruta' => Pages\HojaRuta::route('/{record}/hoja-ruta'),
        ];
    }
}
