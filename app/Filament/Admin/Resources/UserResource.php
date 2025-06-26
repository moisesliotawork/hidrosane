<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages;
use App\Filament\Admin\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('empleado_id')
                    ->label('ID Empleado (3 dígitos)')
                    ->required()
                    ->length(3)
                    ->numeric()
                    ->unique(table: User::class, column: 'empleado_id')
                    ->rules(['regex:/^\d{3}$/']),

                TextInput::make("name")->required()->label('Nombre'),
                TextInput::make("email")->required(),
                TextInput::make("last_name")->label('Apellidos')->required(),
                TextInput::make('password')
                    ->label('Contraseña')
                    ->password() // Oculta el texto por defecto
                    ->revealable() // Permite mostrar/ocultar la contraseña
                    ->hiddenOn('edit') // Oculta en edición (manejaremos la edición por separado)
                    ->required(),
                TextInput::make('new_password')
                    ->label('Nueva Contraseña')
                    ->password()
                    ->revealable()
                    ->visibleOn('edit'),
                Select::make('role')
                    ->label('Rol')
                    ->options([
                        'admin' => 'ADMIN',
                        'head_of_room' => 'JEFE DE SALA',
                        'teleoperator' => 'TELEOPERADOR',
                        'commercial' => 'COMERCIAL',
                    ])
                    ->required()
                    ->searchable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('nombre'),
                TextColumn::make('email')->label('correo electrónico')
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
