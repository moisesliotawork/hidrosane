<?php

namespace App\Filament\Gerente\Resources;

use App\Filament\Gerente\Resources\UserResource\Pages;
use App\Filament\Gerente\Resources\UserResource\RelationManagers;
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
use App\Enums\UserRole;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Usuarios';
    protected static ?string $modelLabel = 'Usuario';
    protected static ?string $pluralModelLabel = 'Usuarios';
    protected static ?string $breadcrumb = 'Usuarios';

    protected static ?int $navigationSort = 8;

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

                TextInput::make("name")->required()->label('Nombres'),
                TextInput::make("last_name")->label('Apellidos')->required(),

                TextInput::make("dni")
                    ->label(label: "DNI")
                    ->required(),
                TextInput::make("email")
                    ->label("Correo")
                    ->required(),
                TextInput::make('direccion')
                    ->label('Dirección')
                    ->maxLength(255)
                    ->nullable(),

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
                    ->timezone('Europe/Madrid')
                    ->live()
                    ->afterStateUpdated(function (Set $set, $state) {
                        // Al seleccionar una fecha, establece la hora a medianoche (00:00:00)
                        $set('alta_empleado', Carbon::parse($state)->startOfDay());
                    })
                    ->dehydrated(),

                DatePicker::make('baja')
                    ->label('Fecha de baja')
                    ->displayFormat('d/m/Y')
                    ->format('Y-m-d')
                    ->seconds(false)        // oculta los segundos
                    ->timezone('Europe/Madrid')
                    ->native(false)
                    ->nullable(),
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
                    ->label('ID Empleado')
                    ->badge()
                    ->color(Color::Blue)
                    ->sortable()
                    ->searchable(
                        query: fn(Builder $query, string $search)
                        => $query->orWhere('users.empleado_id', 'like', "%{$search}%")
                    ),

                TextColumn::make('dni')
                    ->label('DNI')
                    ->sortable()
                    ->searchable(
                        query: fn(Builder $query, string $search)
                        => $query->orWhere('users.dni', 'like', "%{$search}%")
                    ),

                TextColumn::make('name')
                    ->label('NOMBRE')
                    ->sortable()
                    ->searchable(
                        query: fn(Builder $query, string $search)
                        => $query->orWhere('users.name', 'like', "%{$search}%")
                    ),
                TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->searchable(
                        query: fn(Builder $query, string $search)
                        => $query->orWhere('users.email', 'like', "%{$search}%")
                    ),

                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable(
                        query: fn(Builder $query, string $search)
                        => $query->orWhere('users.phone', 'like', "%{$search}%")
                    )
                    ->html()
                    ->formatStateUsing(
                        fn($state) =>
                        '<span style="font-size: 1rem; font-weight: bold;">' .
                        chunk_split(str_replace(' ', '', $state), 3, ' ') .
                        '</span>'
                    ),

                TextColumn::make('role')
                    ->label('Rol')
                    ->badge()
                    ->getStateUsing(fn(User $record) => $record->getRoleNames()->first() ?? null)
                    ->formatStateUsing(function (?string $state) {
                        $enum = $state ? UserRole::tryFrom($state) : null;
                        return $enum?->label() ?? ($state ? ucfirst(str_replace('_', ' ', $state)) : '—');
                    })
                    ->color(fn(?string $state) => UserRole::tryFrom((string) $state)?->filamentColor() ?? 'gray')
                    ->sortable(query: function (Builder $query, string $direction) {
                        $modelHasRoles = config('permission.table_names.model_has_roles');
                        $roleModel = config('permission.models.role');
                        $rolesTable = (new $roleModel)->getTable();

                        return $query
                            ->leftJoin("{$modelHasRoles} as mhr", function ($join) {
                                $join->on('mhr.model_id', '=', 'users.id')
                                    ->where('mhr.model_type', '=', User::class);
                            })
                            ->leftJoin("{$rolesTable} as r", 'r.id', '=', 'mhr.role_id')
                            ->orderBy('r.name', $direction)
                            ->select('users.*');
                    }),
                TextColumn::make('alta_empleado')
                    ->label('Fecha de alta')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->paginated(false)
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
