<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\InfoUserResource\Pages;
use App\Models\User;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Builder;

class InfoUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-information-circle';
    protected static ?string $navigationLabel = 'Info Usuarios';
    protected static ?string $modelLabel = 'Info Usuario';
    protected static ?string $pluralModelLabel = 'Info Usuarios';
    protected static ?string $breadcrumb = 'Info Usuarios';
    protected static ?string $slug = 'info-users';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Identificación')->schema([
                TextInput::make('empleado_id')
                    ->label('ID Empleado')
                    ->disabled(),
                TextInput::make('name')
                    ->label('Nombre')
                    ->disabled(),
                TextInput::make('last_name')
                    ->label('Apellido')
                    ->disabled(),
                TextInput::make('email')
                    ->label('Correo')
                    ->disabled(),
            ])->columns(2),

            Section::make('Acceso y notas')->schema([
                TextInput::make('clave')
                    ->label('Clave')
                    ->maxLength(255)
                    ->nullable(),
                Textarea::make('informacion_general')
                    ->label('Información General')
                    ->rows(6)
                    ->columnSpanFull()
                    ->nullable(),
            ])->columns(2),

            Section::make('Fechas')->schema([
                DatePicker::make('alta_empleado')
                    ->label('Fecha de alta')
                    ->displayFormat('d/m/Y')
                    ->native(false)
                    ->timezone('Europe/Madrid'),
                DatePicker::make('baja')
                    ->label('Fecha de baja')
                    ->displayFormat('d/m/Y')
                    ->native(false)
                    ->timezone('Europe/Madrid')
                    ->nullable(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('empleado_id')
            ->columns([
                TextColumn::make('empleado_id')
                    ->label('ID Empleado')
                    ->badge()
                    ->color(Color::Blue)
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->state(fn(User $r) => strtoupper(trim("{$r->name} {$r->last_name}")))
                    ->extraAttributes(['class' => 'font-bold'])
                    ->searchable(query: fn($query, string $search) => $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                    )
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Correo')
                    ->badge()
                    ->color('warning')
                    ->searchable(),
                TextColumn::make('clave')
                    ->label('Clave')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('informacion_general')
                    ->label('Información General')
                    ->limit(80)
                    ->tooltip(fn($state) => $state)
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('alta_empleado')
                    ->label('Fecha Alta')
                    ->date('d/m/Y')
                    ->badge()
                    ->color('success')
                    ->sortable(),
                TextColumn::make('baja')
                    ->label('Fecha Baja')
                    ->date('d/m/Y')
                    ->badge()
                    ->color('danger')
                    ->sortable(),
            ])
            ->paginated(false)
            ->filters([])
            ->actions([
                \Filament\Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInfoUsers::route('/'),
            'edit'  => Pages\EditInfoUser::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
