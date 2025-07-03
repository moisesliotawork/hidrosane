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
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Set;
use Illuminate\Support\Carbon;
use Filament\Support\Colors\Color;

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
                        'gerente_general' => 'GERENTE GENERAL',
                        'delivery' => 'REPARTIDOR',
                        'delegate' => 'DELEGADO',
                        'team_leader' => 'JEFE DE EQUIPO',
                        'sales_manager' => 'JEFE DE VENTAS',
                        "app_support" => "SOPORTE",
                    ])
                    ->required()
                    ->searchable(),
                DatePicker::make('alta_empleado')
                    ->label('Fecha de alta')
                    ->displayFormat('d/m/Y')
                    ->format('Y-m-d')
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (Set $set, $state) {
                        // Al seleccionar una fecha, establece la hora a medianoche (00:00:00)
                        $set('alta_empleado', Carbon::parse($state)->startOfDay());
                    })
                    ->dehydrated(),
                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->required()
                    ->maxLength(11)
                    ->minLength(11)
                    ->label('Teléfono')
                    ->mask('999 999 999')
                    ->validationMessages([
                        'required' => 'El telefono es obligatorio',
                        'min' => 'Debe tener exactamente 9 cifras',
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('empleado_id')
                    ->badge()
                    ->color(Color::Blue)
                    ->label('ID Empleado')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')->label('nombre'),
                TextColumn::make('email')->label('correo electrónico'),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->label('Teléfono')
                    ->html()
                    ->formatStateUsing(fn($state) => '<span style="font-size: 1rem; font-weight: bold;">' .
                        chunk_split(str_replace(' ', '', $state), 3, ' ') . '</span>'),

                TextColumn::make('role')
                    ->label('Rol')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'admin' => 'info',
                        'gerente_general' => 'info',
                        'head_of_room' => 'pink',
                        'commercial' => 'success',
                        'teleoperator' => 'pink',
                        'delivery' => 'orange',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'admin' => 'ADMIN',
                        'gerente_general' => 'GERENTE GENERAL',
                        'head_of_room' => 'JEFE DE SALA',
                        'commercial' => 'COMERCIAL',
                        'teleoperator' => 'TELEOPERADOR',
                        'delivery' => 'REPARTIDOR',
                        'delegate' => 'DELEGADO',
                        'team_leader' => 'JEFE DE EQUIPO',
                        'sales_manager' => 'JEFE DE VENTAS',
                        "app_support" => "SOPORTE",
                        default => strtoupper($state),
                    }),
                TextColumn::make('alta_empleado')
                    ->label('Fecha de alta')
                    ->date('d/m/Y')
                    ->sortable(),
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
