<?php

namespace App\Filament\Gerente\Resources;

use App\Filament\Gerente\Resources\SupervisionResource\Pages;
use App\Filament\Gerente\Resources\SupervisionResource\RelationManagers;
use App\Models\Supervision;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SupervisionResource extends Resource
{
    protected static ?string $model = Supervision::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Supervisiones';
    protected static ?string $pluralModelLabel = 'Supervisiones';
    protected static ?string $modelLabel = 'Supervisión';
    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // SUPERVISOR (commercial / team_leader), label: empleado_id - name
                Forms\Components\Select::make('supervisor_id')
                    ->label('Supervisor')
                    ->options(
                        fn() => User::query()
                            ->whereHas('roles', fn($q) => $q->whereIn('name', ['commercial', 'team_leader']))
                            ->orderBy('empleado_id')
                            ->get()
                            ->mapWithKeys(fn(User $u) => [
                                $u->id => "{$u->empleado_id} - {$u->name}",
                            ])
                    )
                    ->searchable()
                    ->preload()
                    ->required(),

                // SUPERVISADO (commercial / team_leader), label: empleado_id - name
                Forms\Components\Select::make('supervisado_id')
                    ->label('Supervisado')
                    ->options(
                        fn() => User::query()
                            ->whereHas('roles', fn($q) => $q->whereIn('name', ['commercial', 'team_leader']))
                            ->orderBy('empleado_id')
                            ->get()
                            ->mapWithKeys(fn(User $u) => [
                                $u->id => "{$u->empleado_id} - {$u->name}",
                            ])
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->rule('different:supervisor_id'),

                Forms\Components\DatePicker::make('start_date')
                    ->label('Fecha de inicio')
                    ->native(false)
                    ->required(),

                Forms\Components\DatePicker::make('end_date')
                    ->label('Fecha de fin')
                    ->native(false)
                    ->required(),

                // author se setea en el backend; no se muestra
                Forms\Components\Hidden::make('author_id')
                    ->dehydrated()
                    ->default(fn() => auth()->id()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),

                Tables\Columns\TextColumn::make('supervisor.empleado_id')
                    ->label('Supervisor')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('supervisado.empleado_id')
                    ->label('Supervisado')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Inicio')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Fin')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('author.name')
                    ->label('Creado por')
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ListSupervisions::route('/'),
            'create' => Pages\CreateSupervision::route('/create'),
            'edit' => Pages\EditSupervision::route('/{record}/edit'),
        ];
    }
}
