<?php

namespace App\Filament\Commercial\Resources;

use App\Filament\Commercial\Resources\NoteResource\Pages;
use App\Models\Note;
use App\Models\PostalCode;
use App\Enums\NoteStatus;
use App\Enums\FuenteNotas;
use App\Enums\HorarioNotas;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Illuminate\Support\Str;
use Carbon\Carbon;

class NoteResource extends Resource
{
    protected static ?string $model = Note::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Notas';

    protected static ?string $modelLabel = 'Nota';

    protected static ?string $pluralModelLabel = 'Notas';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('customer_id'),
                Forms\Components\Hidden::make('comercial_id'),

                Forms\Components\Section::make('Información Personal')
                    ->schema([
                        Forms\Components\TextInput::make('first_names')
                            ->disabled()
                            ->maxLength(255)
                            ->label('Nombres')
                            ->validationMessages([
                                'required' => 'Los nombres son obligatorios',
                            ]),

                        Forms\Components\TextInput::make('last_names')
                            ->disabled()
                            ->maxLength(255)
                            ->label('Apellidos')
                            ->validationMessages([
                                'required' => 'Los apellidos son obligatorios',
                            ]),

                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->disabled()
                            ->maxLength(11)
                            ->minLength(11)
                            ->label('Teléfono')
                            ->mask('999 999 999')
                            ->validationMessages([
                                'required' => 'El telefono es obligatorio',
                                'min' => 'Debe tener exactamente 9 cifras',
                            ]),

                        Forms\Components\TextInput::make('secondary_phone')
                            ->tel()
                            ->disabled()
                            ->maxLength(11)
                            ->minLength(11)
                            ->mask('999 999 999')
                            ->label('Teléfono secundario (opcional)')
                            ->validationMessages([
                                'min' => 'Debe tener exactamente 9 cifras',
                            ]),

                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->disabled()
                            ->maxLength(255)
                            ->label('Correo electrónico'),

                        Forms\Components\TextInput::make('age')
                            ->numeric()
                            ->disabled()
                            ->maxLength(20)
                            ->label('Edad')
                            ->validationMessages([
                                'required' => 'La edad es obligatoria',
                            ]),
                    ])->columns(2),

                Forms\Components\Section::make('Información de Contacto')
                    ->schema([
                        Forms\Components\Select::make('postal_code_id')
                            ->label('Código postal')
                            ->disabled()
                            ->options(function () {
                                return PostalCode::query()
                                    ->select('postal_codes.id', 'postal_codes.code', 'cities.title as city_title')
                                    ->join('cities', 'cities.id', '=', 'postal_codes.city_id')
                                    ->orderBy('cities.title')
                                    ->orderBy('postal_codes.code')
                                    ->limit(500) // Limitar resultados para mejor rendimiento
                                    ->get()
                                    ->mapWithKeys(fn($item) => [
                                        $item->id => "{$item->city_title} - {$item->code}"
                                    ]);
                            })
                            ->searchable(['code', 'city.title'])
                            ->preload()
                            ->native(false)
                            ->validationMessages([
                                'required' => 'El código postal es obligatorio',
                            ]),
                        Forms\Components\TextInput::make('primary_address')
                            ->disabled()
                            ->maxLength(255)
                            ->label('Dirección principal'),

                        Forms\Components\TextInput::make('secondary_address')
                            ->maxLength(255)
                            ->disabled()
                            ->label('Dirección secundaria (opcional)'),

                        Forms\Components\TextInput::make('parish')
                            ->maxLength(255)
                            ->disabled()
                            ->label('Parroquia (opcional)'),
                    ])->columns(2),

                Forms\Components\Section::make('Gestión Comercial')
                    ->schema([

                        Forms\Components\Select::make('fuente')
                            ->options(FuenteNotas::options())
                            ->disabled()
                            ->native(false)
                            ->label('Fuente de la nota')
                            ->hidden(fn(string $operation): bool => $operation === 'create'),

                        Forms\Components\Select::make('status')
                            ->options(NoteStatus::options())
                            ->disabled()
                            ->native(false)
                            ->live()
                            ->label('Estado')
                            ->validationMessages([
                                'required' => 'El estado es obligatorio',
                            ]),

                    ]),

                Forms\Components\Section::make('Visita')
                    ->schema([
                        Forms\Components\DatePicker::make('visit_date')
                            ->disabled()
                            ->label('Fecha de visita')
                            ->hidden(fn(Forms\Get $get): bool =>
                                $get('status') !== NoteStatus::CONTACTED->value),

                        Forms\Components\Select::make('visit_schedule')
                            ->options(HorarioNotas::options())
                            ->label('Horario de visita')
                            ->default(HorarioNotas::TD->value) // Default TD
                            ->native(false)
                            ->searchable()
                            ->disabled()
                            ->hidden(fn(Forms\Get $get): bool =>
                                $get('status') !== NoteStatus::CONTACTED->value),
                    ])
                    ->columns(2)
                    ->hidden(fn(Forms\Get $get): bool =>
                        $get('status') !== NoteStatus::CONTACTED->value),

                Forms\Components\Section::make('Historial de Anotaciones de Visita')
                    ->schema([
                        Forms\Components\Grid::make()
                            ->schema([
                                Forms\Components\Textarea::make('anotacionesVisitasDisplay')
                                    ->label('')
                                    ->disabled()
                                    ->formatStateUsing(function ($state, Note $record) {
                                        return $record->anotacionesVisitas
                                            ->map(function ($anotacion) {
                                                $empleadoId = $anotacion->autor->empleado_id ?? 'SIN-ID';
                                                $fechaHora = Carbon::parse($anotacion->created_at)->format('d/m/Y H:i');
                                                return "[$empleadoId] $fechaHora - {$anotacion->asunto}: {$anotacion->cuerpo}";
                                            })
                                            ->join("\n");
                                    })
                                    ->columnSpanFull()
                                    ->rows(5)
                            ])
                    ])
                    ->collapsible()
                    ->hidden(fn(string $operation): bool => $operation === 'create'),

                Forms\Components\Section::make('Observaciones')
                    ->schema([
                        Forms\Components\Repeater::make('observations')
                            ->label("")
                            ->relationship() // Esto es clave para el funcionamiento correcto
                            ->schema([
                                Forms\Components\Hidden::make('id'),
                                Forms\Components\Hidden::make('author_id')
                                    ->default(auth()->id()),
                                Forms\Components\Textarea::make('observation')
                                    ->label('')
                                    ->placeholder('Escribe una observación')
                                    ->columnSpanFull()
                                    ->disabled(function ($get, $set, $state, $record) {
                                        // Si es una nueva observación (no tiene ID), permitir edición
                                        if (empty($record?->getKey())) {
                                            return false;
                                        }
                                        // Si ya existe, solo permitir edición si es del usuario actual
                                        return $record->author_id !== auth()->id();
                                    })
                                    ->dehydrated(),
                            ])
                            ->addActionLabel('Añadir observación')
                            ->defaultItems(1)
                            ->collapsible()
                            ->collapsed()
                            ->columnSpanFull()
                            ->itemLabel(function (array $state): ?string {
                                $author = isset($state['author_id'])
                                    ? \App\Models\User::find($state['author_id'])
                                    : auth()->user();

                                $date = isset($state['created_at'])
                                    ? Carbon::parse($state['created_at'])->format('d/m/y')
                                    : now()->format('d/m/y');

                                $observationText = $state['observation'] ?? 'Nueva observación';
                                $limitedObservation = Str::limit($observationText, 30);

                                return "{$date}: {$limitedObservation}";
                            })
                            ->deleteAction(
                                fn(Forms\Components\Actions\Action $action) => $action
                                    ->requiresConfirmation()
                                    ->hidden(function ($record) {
                                        // Permitir eliminar si es nueva observación
                                        if (empty($record->getKey())) {
                                            return false;
                                        }
                                        // Ocultar si el autor no es el usuario actual
                                        return $record->author_id !== auth()->id();
                                    })
                            ),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([20, 25, 30, 40, 'all'])
            ->columns([
                Tables\Columns\TextColumn::make('nro_nota')
                    ->searchable()
                    ->badge()
                    ->color(Color::Gray)
                    ->label('# Nota'),

                Tables\Columns\TextColumn::make('user.empleado_id')
                    ->searchable()
                    ->badge()
                    ->color(Color::Pink)
                    ->label('T. Op.'),

                Tables\Columns\TextColumn::make('customer.name')
                    ->searchable()
                    ->label('Nombre Cliente'),

                Tables\Columns\TextColumn::make('customer.phone')
                    ->searchable()
                    ->label('Teléfono')
                    ->formatStateUsing(fn($state) => chunk_split(str_replace(' ', '', $state), 3, ' ')),

                Tables\Columns\TextColumn::make('customer.postalCode.code')
                    ->label('CP'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(NoteStatus $state): string => $state->getColor())
                    ->formatStateUsing(fn(NoteStatus $state): string => $state->label())
                    ->sortable()
                    ->label('Estado'),

                Tables\Columns\TextColumn::make('fecha_asig')
                    ->label('Asig.')
                    ->sortable(),

                Tables\Columns\TextColumn::make('visit_schedule')
                    ->badge()
                    ->color(Color::Gray)
                    ->label('Horario')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(NoteStatus::options())
                    ->label('Estado'),
            ])
            ->actions([

            ])
            ->bulkActions([
                // No bulk actions for commercial panel
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotes::route('/'),
            //'view' => Pages\ViewNote::route('/{record}'),
            'edit' => Pages\EditNote::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('comercial_id', auth()->id());
    }

    public static function canCreate(): bool
    {
        return false;
    }


}