<?php

namespace App\Filament\HeadOfRoom\Resources;

use App\Filament\HeadOfRoom\Resources\TeleoperadoraResource\Pages;
use App\Filament\HeadOfRoom\Resources\TeleoperadoraResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\TextColumn;
use App\Enums\EstadoTerminal;


class TeleoperadoraResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Teleoperadoras';
    protected static ?string $pluralModelLabel = 'Teleoperadoras';
    protected static ?string $modelLabel = 'Teleoperadora';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('empleado_id')
                    ->label('Empleado')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('full_name')
                    ->label('Nombre')
                    ->state(fn($record) => "{$record->name} {$record->last_name}")
                    ->sortable(query: function (Builder $query, string $direction) {
                        $query->orderBy('users.name', $direction)
                            ->orderBy('users.last_name', $direction);
                    })
                    ->searchable(query: function (Builder $query, string $search) {
                        $query->where(function ($q) use ($search) {
                            $q->where('users.name', 'like', "%{$search}%")
                                ->orWhere('users.last_name', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('confirmadas_count')
                    ->label('CONFIRMADAS')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('vendidas_count')
                    ->label('VENTAS')
                    ->sortable()
                    ->badge()
                    ->color('success'),

                TextColumn::make('total_cv')
                    ->label('TOTAL')
                    ->state(fn($record) => ($record->confirmadas_count ?? 0) + ($record->vendidas_count ?? 0))
                    ->sortable()
                    ->badge()
                    ->color('primary'),
            ])
            ->filters([
                //
            ])
            ->actions([

            ])
            ->bulkActions([
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeleoperadoras::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->role('teleoperator') // Spatie\Permission
            ->withCount([
                // Notas creadas por la teleoperadora (user_id)
                'notes as confirmadas_count' => fn($q) =>
                    $q->where('estado_terminal', EstadoTerminal::CONFIRMADO->value),

                'notes as vendidas_count' => fn($q) =>
                    $q->where('estado_terminal', EstadoTerminal::VENTA->value),
            ]);
    }

    public static function canEdited(): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
