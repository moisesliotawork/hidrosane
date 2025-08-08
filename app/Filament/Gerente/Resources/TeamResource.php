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
use Illuminate\Support\Facades\DB;

class TeamResource extends Resource
{
    protected static ?string $model = Team::class;


    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $modelLabel = 'Equipo';
    protected static ?string $pluralModelLabel = 'Equipos';
    protected static ?string $navigationLabel = 'Equipos';
    protected static ?string $breadcrumb = 'Equipos';


    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\FileUpload::make('foto')
                ->label('Foto del equipo')
                ->image()
                ->imagePreviewHeight('200')
                ->directory('equipos')
                ->disk('public')
                ->visibility('public')
                ->columnSpanFull(),


            Forms\Components\TextInput::make('name')
                ->label('Nombre del equipo')
                ->required()
                ->columnSpanFull(),

            Forms\Components\Textarea::make('description')
                ->label('Descripción')
                ->rows(2)
                ->columnSpanFull(),

            Forms\Components\Select::make('team_leader_id')
                ->label('Líder de Equipo')
                ->options(function () {
                    // IDs de líderes ya asignados
                    $takenLeaders = Team::query()
                        ->pluck('team_leader_id')
                        ->filter() // descartar null
                        ->toArray();

                    // IDs de miembros ya asignados en cualquier equipo
                    $takenMembers = DB::table('user_team')
                        ->pluck('user_id')
                        ->toArray();

                    $excluded = array_unique(array_merge($takenLeaders, $takenMembers));

                    return User::role('commercial')
                        ->whereNotIn('id', $excluded)
                        ->orderBy('empleado_id')
                        ->get()
                        ->mapWithKeys(fn($u) => [
                            $u->id => "{$u->empleado_id} {$u->name} {$u->last_name}"
                        ]);
                })
                ->searchable()
                ->preload()
                ->required()
                ->columnSpanFull(),

            Forms\Components\Repeater::make('miembros')
                ->label('Miembros del equipo')
                ->schema([
                    Forms\Components\Select::make('user_id')
                        ->label('Usuario')
                        ->options(function (Forms\Get $get) {
                            $leaderId = $get('../../team_leader_id');

                            // Todos los líderes y miembros de otros equipos
                            $takenLeaders = Team::query()
                                ->pluck('team_leader_id')
                                ->filter()
                                ->toArray();

                            $takenMembers = DB::table('user_team')
                                ->pluck('user_id')
                                ->toArray();

                            // Excluir al líder actual también
                            $excluded = array_unique(array_merge($takenLeaders, $takenMembers, [$leaderId]));

                            return User::role('commercial')
                                ->whereNotIn('id', $excluded)
                                ->orderBy('empleado_id')
                                ->get()
                                ->mapWithKeys(fn($u) => [
                                    $u->id => "{$u->empleado_id} {$u->name} {$u->last_name}"
                                ]);
                        })
                        ->searchable()
                        ->required(),
                ])
                ->createItemButtonLabel('Agregar miembro')
                ->columnSpanFull(),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\ImageColumn::make('foto_url')
                    ->label('Foto')
                    ->height('80px')
                    ->width('80px')
                    ->circular(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Equipo'),

                Tables\Columns\TextColumn::make('teamLeader.name')
                    ->label('Líder'),

                Tables\Columns\TextColumn::make('members')
                    ->label('Miembros')
                    ->badge()
                    ->color('primary')
                    ->getStateUsing(function ($record) {
                        return $record->members->map(fn($m) => $m->display_name)->toArray();
                    })
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

    protected static function getTableQuery(): Builder
    {
        return parent::getTableQuery()->where('deleted', false);
    }

}
