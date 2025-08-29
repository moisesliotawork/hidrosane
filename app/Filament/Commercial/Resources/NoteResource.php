<?php

namespace App\Filament\Commercial\Resources;

use App\Filament\Commercial\Resources\NoteResource\Pages;
use App\Models\Note;
use App\Models\PostalCode;
use App\Enums\NoteStatus;
use App\Enums\FuenteNotas;
use App\Enums\HorarioNotas;
use App\Enums\EstadoTerminal;
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
use App\Models\Team;

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

                        Forms\Components\DatePicker::make('fecha_nac')
                            ->label('Fecha de nacimiento')
                            ->native(false)
                            ->timezone('Europe/Madrid')
                            ->disabled()         // solo lectura en Commercial
                            ->dehydrated(false)  // NO enviar al backend desde este form
                            ->reactive()
                            ->afterStateHydrated(function ($state, Forms\Set $set) {
                                $set('age', $state ? Carbon::parse($state)->age : null);
                            }),

                        Forms\Components\TextInput::make('age')
                            ->numeric()
                            ->label('Edad')
                            ->disabled()         // solo lectura
                            ->dehydrated(false), // NO enviar al backend desde este form

                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->disabled()
                            ->maxLength(255)
                            ->label('Correo electrónico'),
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

                        Forms\Components\TextInput::make('ayuntamiento')
                            ->maxLength(255)
                            ->required()
                            ->label('Ayuntamiento'),
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
                            ->timezone('Europe/Madrid')
                            ->native(false)
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
                    ->sortable()
                    ->color(Color::Gray)
                    ->label('# Nota'),

                Tables\Columns\TextColumn::make('comercial.empleado_id')
                    ->label('Comercial ID')
                    ->badge()
                    ->color(Color::Blue)
                    ->sortable(),

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
                    ->formatStateUsing(fn($state) => chunk_split(str_replace(' ', '', $state), 3, ' ')),

                Tables\Columns\TextColumn::make('customer.postalCode.code')
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
                Tables\Filters\SelectFilter::make('status')
                    ->options(NoteStatus::options())
                    ->label('Estado'),
            ])
            ->actions([
                Tables\Actions\Action::make('assignCommercial')
                    ->label('')
                    ->icon('heroicon-s-user-plus')
                    ->form([
                        Forms\Components\Select::make('comercial_id')
                            ->label('Seleccionar Comercial')
                            ->options(function () {
                                $users = \App\Models\User::query()
                                    ->select('users.id', 'users.name', 'users.last_name', 'users.empleado_id')
                                    ->with(['roles:id,name'])                 // cargamos roles
                                    ->role(['commercial', 'team_leader'])     // cualquiera de los dos
                                    ->orderBy('empleado_id')
                                    ->get()
                                    ->unique('id');

                                $options = $users->mapWithKeys(
                                    /** @param \App\Models\User $user */
                                    function (\App\Models\User $user) {
                                        $hasTL = $user->roles->contains('name', 'team_leader');
                                        $hasCOM = $user->roles->contains('name', 'commercial');

                                        $tag = $hasTL && $hasCOM ? 'TL/COM' : ($hasTL ? 'TL' : 'COM');

                                        return [
                                            $user->id => "{$user->empleado_id} {$user->name} {$user->last_name} ({$tag})",
                                        ];
                                    }
                                )->toArray();

                                return [null => 'Sin asignar'] + $options;
                            })
                            ->searchable()
                            ->native(false),

                        Forms\Components\DatePicker::make('assignment_date')
                            ->label('Fecha de asignación')
                            ->hint('Si se deja vacío, se usará la fecha actual')
                            ->required(false),
                    ])
                    ->action(function (Note $record, array $data): void {
                        try {
                            $record->update([
                                'comercial_id' => $data['comercial_id'] ?? null,
                                'assignment_date' => ($data['comercial_id'] ?? null)
                                    ? ($data['assignment_date'] ?? now())
                                    : null,
                            ]);

                            $message = is_null($data['comercial_id'] ?? null)
                                ? 'Comercial removido correctamente'
                                : 'Comercial asignado correctamente: ' . \App\Models\User::find($data['comercial_id'])->name;

                            Notification::make()
                                ->title($message)
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error al actualizar comercial')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn(): bool => auth()->user()->hasRole('team_leader')),

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
        $user = auth()->user();

        // 1) IDs base: propio usuario
        $ids = collect([$user->id]);

        // 2) Si es líder, añadir miembros
        if ($user->hasRole('team_leader')) {
            $team = Team::where('team_leader_id', $user->id)->first();
            if ($team) {
                $ids = $ids->merge(
                    $team->members()->pluck('users.id')
                )->unique();
            }
        }

        // 3) Construir query inicial
        $query = parent::getEloquentQuery()
            ->whereIn('comercial_id', $ids->all());

        // 4) Detectar tab activo: “com_{ID}”
        $active = request()->query('activeTab', '');

        if (Str::startsWith($active, 'com_')) {
            $comId = (int) Str::after($active, 'com_');

            if ($comId > 0) {
                $query->where('comercial_id', $comId);
            }
        }

        // 5) Filtrar siempre estado_terminal vacío ó ''
        $query->where(function ($q) {
            $q->whereNull('estado_terminal')
                ->orWhere('estado_terminal', '');
        });

        return $query;
    }

    public static function canCreate(): bool
    {
        return false;
    }


}