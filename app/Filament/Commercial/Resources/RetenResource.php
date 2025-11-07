<?php

namespace App\Filament\Commercial\Resources;

use App\Filament\Commercial\Resources\RetenResource\Pages;
use App\Filament\Commercial\Resources\RetenResource\RelationManagers;
use App\Models\Note;
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
use Filament\Forms\Get;

class RetenResource extends Resource
{
    protected static ?string $model = Note::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Reten';

    protected static ?string $modelLabel = 'Reten';

    protected static ?string $pluralModelLabel = 'Reten';

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

                        // Teléfono
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
                            ])
                            ->visible(
                                fn(Get $get, ?Note $record) =>
                                auth()->user()?->hasRole('team_leader')
                                || ($record?->canShowPhone() ?? (bool) $get('show_phone'))
                            ),

                        Forms\Components\TextInput::make('secondary_phone')
                            ->tel()
                            ->disabled()
                            ->maxLength(11)
                            ->minLength(11)
                            ->mask('999 999 999')
                            ->label('Teléfono secundario (opcional)')
                            ->validationMessages([
                                'min' => 'Debe tener exactamente 9 cifras',
                            ])
                            ->visible(
                                fn(Get $get, ?Note $record) =>
                                auth()->user()?->hasRole('team_leader')
                                || ($record?->canShowPhone() ?? (bool) $get('show_phone'))
                            ),

                        Forms\Components\TextInput::make('edadTelOp')
                            ->numeric()
                            ->label('Edad Tel. Op')
                            ->required()
                            ->maxValue(120)
                            ->minValue(0),

                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->disabled()
                            ->maxLength(255)
                            ->label('Correo electrónico'),
                    ])->columns(2),

                Forms\Components\Section::make('Información de Contacto')
                    ->schema([
                        Forms\Components\TextInput::make('postal_code')
                            ->required()
                            ->maxLength(255)
                            ->label('Codigo Postal'),

                        Forms\Components\TextInput::make('nro_piso')
                            ->required()
                            ->maxLength(10)
                            ->label('#Piso'),

                        Forms\Components\TextInput::make('ciudad')
                            ->required()
                            ->maxLength(255)
                            ->label('Ciudad'),

                        Forms\Components\TextInput::make('provincia')
                            ->required()
                            ->maxLength(255)
                            ->label('Provincia'),

                        Forms\Components\Textarea::make('primary_address')
                            ->disabled()
                            ->rows(4)
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
                    ->label('Teléfono')
                    ->toggleable()
                    ->toggledHiddenByDefault(false)
                    ->state(function (Note $record) {
                        $viewer = auth()->user();
                        $canSee = $viewer?->hasRole('team_leader') ? true : $record->canShowPhone();

                        return $canSee ? ($record->customer?->phone) : '—';
                    })
                    ->formatStateUsing(function (?string $state) {
                        if (blank($state) || $state === '—') {
                            return '—';
                        }
                        $digits = preg_replace('/\s+/', '', $state);
                        return trim(chunk_split($digits, 3, ' ')); // 999 999 999
                    })
                    ->searchable(false)
                    ->alignCenter(),


                Tables\Columns\TextColumn::make('customer.postal_code')
                    ->toggleable()
                    ->toggledHiddenByDefault(true)
                    ->label('CP'),

                Tables\Columns\TextColumn::make('customer.primary_address')
                    ->label('Dirección')
                    ->badge()
                    ->color(Color::Gray)
                    ->state(fn(Note $record) => $record->customer?->primary_address ?: '—')
                    ->formatStateUsing(function (?string $state) {
                        if (blank($state)) {
                            return '—';
                        }
                        // Inserta salto de línea cada 100 caracteres
                        return wordwrap($state, 100, "\n", true);
                    })
                    // Permite que el badge muestre varias líneas
                    ->extraAttributes([
                        'class' => 'whitespace-pre-wrap break-words', // respeta \n y corta palabras largas
                    ])
                    // Tooltip con el texto completo
                    ->tooltip(fn($state) => $state ?: null)
                    // Hacerla buscable en la tabla (en la relación customers)
                    ->searchable(query: function (Builder $query, string $search) {
                        $query->whereHas('customer', function (Builder $q) use ($search) {
                            $q->where('customers.primary_address', 'like', "%{$search}%");
                        });
                    })
                    ->toggleable()
                    ->toggledHiddenByDefault(true),


                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->toggleable()
                    ->toggledHiddenByDefault(true)
                    ->color(fn(NoteStatus $state): string => $state->getColor())
                    ->formatStateUsing(fn(NoteStatus $state): string => $state->label())
                    ->sortable()
                    ->label('Estado'),

                Tables\Columns\TextColumn::make('assignment_date')
                    ->toggleable()
                    ->toggledHiddenByDefault(false)
                    ->label('Asig.')
                    ->date("d/m/Y")
                    ->sortable(),

                Tables\Columns\TextColumn::make('visit_schedule')
                    ->badge()
                    ->toggleable()
                    ->toggledHiddenByDefault(true)
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
                                    ->with(['roles:id,name'])
                                    ->role(['commercial', 'team_leader', 'sales_manager'])
                                    ->orderBy('empleado_id')
                                    ->get()
                                    ->unique('id');

                                $options = $users->mapWithKeys(function (\App\Models\User $user) {
                                    $hasTL = $user->roles->contains('name', 'team_leader');
                                    $hasCOM = $user->roles->contains('name', 'commercial');
                                    $hasJV = $user->roles->contains('name', 'sales_manager');
                                    $tag = $hasTL && $hasCOM && $hasJV ? 'TL/COM' : ($hasCOM ? 'COM' : 'TL');

                                    return [
                                        $user->id => "{$user->empleado_id} {$user->name} {$user->last_name} ({$tag})",
                                    ];
                                })->toArray();

                                // <<--- NUEVO: opción especial
                                return [
                                    '__RETEN__' => 'COMERCIAL RETEN',
                                    null => 'Sin asignar',
                                ] + $options;
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
                            // <<--- NUEVO: si eligieron COMERCIAL RETEN, solo marcar reten=true y salir
                            if (($data['comercial_id'] ?? null) === '__RETEN__') {
                                $record->update(['reten' => true]);

                                Notification::make()
                                    ->title('Marcada como COMERCIAL RETEN')
                                    ->success()
                                    ->send();

                                return;
                            }

                            // Comportamiento normal de asignación
                            $record->update([
                                'comercial_id' => $data['comercial_id'] ?? null,
                                'assignment_date' => ($data['comercial_id'] ?? null)
                                    ? ($data['assignment_date'] ?? now())
                                    : null,
                                'reten' => false,
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
                \Filament\Tables\Actions\BulkAction::make('enviarASala')
                    ->label('Enviar a Oficina')
                    ->icon('heroicon-o-building-office-2')
                    ->color('pink')
                    ->requiresConfirmation()
                    ->modalHeading('Enviar a Oficina')
                    ->modalDescription('Se enviarán a OFICINA las notas seleccionadas que no tengan estado terminal o su estado terminal sea AUSENTE. Las notas con VENTA / CONFIRMADO / NULO se omitirán.')
                    ->action(function (iterable $records): void {
                        $allIds = collect($records)->pluck('id')->all();

                        // Elegibles: sin venta y TN ∈ { null, '', 'ausente' }
                        $eligible = Note::query()
                            ->whereIn('id', $allIds)
                            ->whereDoesntHave('venta')
                            ->where(function ($q) {
                            $q->whereNull('estado_terminal')
                                ->orWhere('estado_terminal', '')
                                ->orWhereRaw("LOWER(TRIM(estado_terminal)) = 'ausente'");
                        })
                            ->pluck('id')
                            ->all();

                        $skipped = count($allIds) - count($eligible);

                        if (empty($eligible)) {
                            Notification::make()
                                ->title('No hay notas válidas para enviar a Oficina')
                                ->body('Todas las seleccionadas tienen venta o su TN es NULO/CONFIRMADO/VENTA.')
                                ->warning()
                                ->send();
                            return;
                        }

                        \DB::transaction(function () use ($eligible) {
                            \App\Models\Note::whereIn('id', $eligible)->update([
                                'estado_terminal' => EstadoTerminal::SALA->value,
                                'printed' => false,
                                'reten' => false
                            ]);
                        });

                        Notification::make()
                            ->title('Notas enviadas a Oficina')
                            ->body('Actualizadas: ' . count($eligible) . ($skipped ? ' • Omitidas: ' . $skipped : ''))
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion()

            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AusenciasRelationManager::class,
        ];
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

        // 5) Filtrar siempre estado_terminal vacío ó '' y sin venta asociada
        // 5) Estado terminal: incluir null, '', y AUSENTE (sin importar may/min/espacios)
        $query->where(function ($q) {
            $q->whereNull('estado_terminal')
                ->orWhere('estado_terminal', '') // vacío exacto
                ->orWhereRaw("LOWER(TRIM(estado_terminal)) = 'ausente'");
        })
            ->whereDoesntHave('venta'); // sin venta

        // 4) Rango de fecha: desde hoy-5 hasta hoy (INCLUSIVO), forzando DATE(...)
        $desde = now()->subDays(5)->toDateString();
        $hasta = now()->toDateString();

        $query->whereBetween(\DB::raw('DATE(assignment_date)'), [$desde, $hasta]);

        $query->where('reten', true);

        return $query;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return $user
            && ($user->hasRole('sales_manager') || $user->hasRole('team_leader'));
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user
            && ($user->hasRole('sales_manager') || $user->hasRole('team_leader'));
    }

}