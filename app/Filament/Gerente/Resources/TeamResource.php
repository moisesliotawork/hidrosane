<?php

namespace App\Filament\Gerente\Resources;

use App\Filament\Gerente\Resources\TeamResource\Pages;
use App\Filament\Gerente\Resources\TeamResource\RelationManagers;
use App\Models\Team;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TeamResource extends Resource
{
    protected static ?string $model = Team::class;


    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Gestión de Equipos';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nombre del equipo')
                ->required(),

            Forms\Components\Textarea::make('description')
                ->label('Descripción')
                ->rows(2),

            Forms\Components\Select::make('team_leader_id')
                ->label('Líder de Equipo')
                ->options(
                    User::role('commercial')
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn($user) => [$user->id => "{$user->empleado_id} {$user->name} {$user->last_name}"])
                )
                ->searchable()
                ->preload()
                ->required(),

            Forms\Components\Repeater::make('members')
                ->relationship('members')
                ->label('Miembros del equipo')
                ->schema([
                    Forms\Components\Select::make('user_id')
                        ->label('Usuario')
                        ->options(
                            User::role('commercial')
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn($user) => [$user->id => "{$user->empleado_id} {$user->name} {$user->last_name}"])
                        )
                        ->required()
                        ->searchable(),

                    // Estos campos no se muestran pero se graban con default
                    Forms\Components\DatePicker::make('pivot.joined_at')
                        ->default(now())
                        ->hidden(),

                    Forms\Components\Toggle::make('pivot.is_active')
                        ->default(true)
                        ->hidden(),
                ])
                ->createItemButtonLabel('Agregar miembro'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListTeams::route('/'),
            'create' => Pages\CreateTeam::route('/create'),
            'edit' => Pages\EditTeam::route('/{record}/edit'),
        ];
    }
}
