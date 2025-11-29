<?php

namespace App\Filament\Teleoperator\Resources;

use App\Filament\Teleoperator\Resources\NoteResource\Pages;
use App\Filament\Teleoperator\Resources\NoteResource\RelationManagers;
use App\Models\Note;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Enums\NoteStatus;
use App\Enums\FuenteNotas;
use App\Enums\HorarioNotas;
use Illuminate\Support\Str;
use Filament\Support\Colors\Color;
use Filament\Notifications\Notification;
use App\Models\Customer;

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
                            ->required()
                            ->maxLength(255)
                            ->label('Nombres')
                            ->validationMessages([
                                'required' => 'Los nombres son obligatorios',
                            ]),

                        Forms\Components\TextInput::make('last_names')
                            ->required()
                            ->maxLength(255)
                            ->label('Apellidos')
                            ->validationMessages([
                                'required' => 'Los apellidos son obligatorios',
                            ]),

                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->required()
                            ->mask('999 999 999')
                            ->label('Teléfono')
                            ->default(function () {
                                $raw = request('phone');
                                if (!$raw)
                                    return null;
                                $digits = preg_replace('/\D+/', '', (string) $raw);
                                if ($digits === '' || strlen($digits) !== 9)
                                    return null;
                                // Mostrar con espacios en el input
                                return implode(' ', str_split($digits, 3));
                            })
                            ->rule(function () {
                                return function (string $attribute, $value, \Closure $fail) {
                                    $digits = preg_replace('/\D+/', '', (string) $value);
                                    if (strlen($digits) !== 9) {
                                        $fail('Debe tener exactamente 9 cifras');
                                    }
                                };
                            })
                            ->dehydrateStateUsing(fn($state) => preg_replace('/\D+/', '', (string) $state))
                            ->dehydrated(true),

                        Forms\Components\TextInput::make('secondary_phone')
                            ->tel()
                            ->mask('999 999 999')
                            ->label('Teléfono secundario (opcional)')
                            ->rule(function () {
                                return function (string $attribute, $value, \Closure $fail) {
                                    if ($value === null || $value === '')
                                        return;
                                    $digits = preg_replace('/\D+/', '', (string) $value);
                                    if ($digits !== '' && strlen($digits) !== 9) {
                                        $fail('Debe tener exactamente 9 cifras');
                                    }
                                };
                            })
                            ->dehydrateStateUsing(function ($state) {
                                $digits = preg_replace('/\D+/', '', (string) $state);
                                return $digits === '' ? null : $digits;
                            })
                            ->dehydrated(true),

                        Forms\Components\TextInput::make('edadTelOp')
                            ->numeric()
                            ->label('Edad Tel. Op')
                            ->required()
                            ->maxValue(120)
                            ->minValue(0),

                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255)
                            ->label('Correo electrónico'),
                    ])->columns(2),

                Forms\Components\Section::make('Información de Contacto')
                    ->schema([

                        Forms\Components\TextInput::make('primary_address')
                            ->required()
                            ->maxLength(255)
                            ->label('Dirección principal'),

                        Forms\Components\TextInput::make('nro_piso')
                            ->required()
                            ->maxLength(20)
                            ->label('No. y Piso'),

                        Forms\Components\TextInput::make('postal_code')
                            ->required()
                            ->maxLength(255)
                            ->label('Codigo Postal'),

                        Forms\Components\TextInput::make('ciudad')
                            ->required()
                            ->maxLength(255)
                            ->label('Ayuntamiento/Localidad'),

                        Forms\Components\TextInput::make('provincia')
                            ->required()
                            ->maxLength(255)
                            ->label('Provincia'),

                        Forms\Components\TextInput::make('secondary_address')
                            ->maxLength(255)
                            ->label('Dirección secundaria (opcional)'),


                    ])->columns(2),

                Forms\Components\Section::make('Gestión Comercial')
                    ->schema([

                        Forms\Components\Select::make('fuente')
                            ->label('Tipo')
                            ->native(false)
                            ->options(FuenteNotas::options()) // sin filtrar opciones
                            // === visibilidad y comportamiento ===
                            ->hidden(fn() => !auth()->user()?->canSeeVipSources())
                            ->dehydrated(fn() => auth()->user()?->canSeeVipSources())   // solo envía el valor si se muestra
                            ->required(fn() => auth()->user()?->canSeeVipSources()),    // solo es requerido si se muestra

                        Forms\Components\Select::make('status')
                            ->options(NoteStatus::options())
                            ->required()
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
                            ->label('Fecha de visita')
                            ->timezone('Europe/Madrid')
                            ->native(false)
                            ->default(now()->addDay()->toDateString()) // Default mañana
                            ->hidden(fn(Forms\Get $get): bool =>
                                $get('status') !== NoteStatus::CONTACTED->value),

                        Forms\Components\Select::make('visit_schedule')
                            ->options(HorarioNotas::options())
                            ->label('Horario de visita')
                            ->default(HorarioNotas::TD->value) // Default TD
                            ->native(false)
                            ->searchable()
                            ->required()
                            ->hidden(fn(Forms\Get $get): bool =>
                                $get('status') !== NoteStatus::CONTACTED->value),
                    ])
                    ->columns(2)
                    ->hidden(fn(Forms\Get $get): bool =>
                        $get('status') !== NoteStatus::CONTACTED->value),

                Forms\Components\Section::make('Observaciones')
                    ->schema([
                        Forms\Components\Repeater::make('observations')
                            ->label("")
                            ->relationship('myObservations')
                            ->schema([
                                Forms\Components\Hidden::make('author_id')
                                    ->default(auth()->id()),
                                Forms\Components\Textarea::make('observation')

                                    ->label('')
                                    ->placeholder('Escribe una observación')
                                    ->columnSpanFull(),
                            ])
                            ->addActionLabel('Añadir observación')
                            ->defaultItems(1)
                            ->collapsible()
                            ->dehydrated(false)
                            ->collapsed()
                            ->columnSpanFull()
                            ->itemLabel(function (array $state): ?string {
                                // Usar el usuario autenticado como fallback
                                $author = auth()->user();

                                // Si hay un author_id en el estado, intentar cargar el usuario
                                if (isset($state['author_id'])) {
                                    $author = \App\Models\User::find($state['author_id']) ?? $author;
                                }

                                // Determinar el rol abreviado
                                $role = 'Tel. Op'; // Por defecto
                                if ($author->hasRole('commercial')) {
                                    $role = 'Com.';
                                } elseif ($author->hasRole('head_of_room')) {
                                    $role = 'Tel. Op';
                                }

                                // Formatear la fecha (usar now() si no hay fecha)
                                $date = now()->format('d/m/y');

                                // Limitar el texto de la observación para que no sea muy largo
                                $observationText = $state['observation'] ?? 'Nueva observación';
                                $limitedObservation = Str::limit($observationText, 30);

                                return "{$author->empleado_id} ({$role}) - {$date}: {$limitedObservation}";
                            }),

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
                    ->sortable()
                    ->badge()
                    ->color(Color::Gray)
                    ->label('# Nota'),

                //Tables\Columns\TextColumn::make('fuente')
                //    ->badge()
                //    ->color(fn(FuenteNotas $state): string => $state->getColor())
                //    ->formatStateUsing(fn(FuenteNotas $state): string => $state->getLabel())
                //    ->label('Tipo'),

                Tables\Columns\TextColumn::make('user.empleado_id')
                    ->searchable()
                    ->badge()
                    ->color(Color::Pink)
                    ->label('T. Op.'),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Nombre Cliente')
                    ->searchable(query: function (Builder $query, string $search) {
                        $query->whereHas('customer', function (Builder $q) use ($search) {
                            $q->where(function (Builder $qq) use ($search) {
                                $qq->where('customers.first_names', 'like', "%{$search}%")
                                    ->orWhere('customers.last_names', 'like', "%{$search}%")
                                    ->orWhereRaw(
                                        "CONCAT(COALESCE(customers.first_names,''),' ',COALESCE(customers.last_names,'')) LIKE ?",
                                        ["%{$search}%"]
                                    );
                            });
                        });
                    }),


                Tables\Columns\TextColumn::make('customer.phone')
                    ->searchable()
                    ->label('Teléfono')
                    ->html()
                    ->formatStateUsing(fn($state) => '<span style="font-size: 1rem; font-weight: bold;">' .
                        chunk_split(str_replace(' ', '', $state), 3, ' ') . '</span>'),

                Tables\Columns\TextColumn::make('customer.postal_code')
                    ->label('CP'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(NoteStatus $state): string => $state->getColor())
                    ->formatStateUsing(fn(NoteStatus $state): string => $state->label())
                    ->sortable()
                    ->label('Estado'),

                Tables\Columns\TextColumn::make('assignment_date')
                    ->label('Asig.')
                    ->date("d/m/Y")
                    ->sortable(),

                Tables\Columns\TextColumn::make('visit_schedule')
                    ->badge()
                    ->color(Color::Gray)
                    ->label('Horario')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label(''),
                //Tables\Actions\Action::make('changeFuente')
                //    ->form([
                //        Forms\Components\Select::make('fuente')
                //            ->options(FuenteNotas::options())
                //            ->required()
                //            ->native(false)
                //            ->label('Nueva Fuente')
                //    ])
                //    ->action(function (Note $record, array $data): void {
                //        $record->fuente = $data['fuente'];
                //        $record->save();
                //    })
                //    ->icon('heroicon-o-pencil-square')
                //    ->modalHeading('Cambiar Fuente')
                //    ->modalButton('Guardar')
                //    ->label('Cambiar Fuente')
            ])
            ->headerActions([
                Tables\Actions\Action::make('buscarTelefono')
                    ->label('Buscar teléfono')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('orange')
                    ->modalHeading('Buscar cliente por teléfono')
                    ->modalSubmitActionLabel('Buscar')
                    ->form([
                        Forms\Components\TextInput::make('phone_query')
                            ->label('INGRESA NUMERO DE TELEFONO')
                            ->tel()
                            ->required()
                            ->mask('999 999 999')
                            ->rule(function () {
                                return function (string $attribute, $value, \Closure $fail) {
                                    $digits = preg_replace('/\D+/', '', (string) $value);
                                    if (strlen($digits) !== 9) {
                                        $fail('Debe tener exactamente 9 cifras.');
                                    }
                                };
                            }),
                    ])
                    ->action(function (array $data) {
                        $digits = preg_replace('/\D+/', '', (string) ($data['phone_query'] ?? ''));

                        if (strlen($digits) !== 9) {
                            Notification::make()
                                ->title('Teléfono inválido')
                                ->body('Debe tener exactamente 9 cifras.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $exists = Customer::query()
                            ->where('phone', $digits)
                            ->orWhere('secondary_phone', $digits)
                            ->exists();

                        if ($exists) {
                            Notification::make()
                                ->title('CLIENTE DUPLICADO')
                                ->body('Ya existe un cliente con ese número de teléfono.')
                                ->warning()
                                ->send();

                            return;
                        }

                        // Si no existe, redirige a crear nota con ?phone=<digits>
                        $url = static::getUrl('create', ['phone' => $digits]);
                        return redirect($url);
                    }),
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id())
            ->whereNull('comercial_id');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotes::route('/'),
            'create' => Pages\CreateNote::route('/create'),
            'edit' => Pages\EditNote::route('/{record}/edit'),
        ];
    }
}
