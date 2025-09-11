<?php

namespace App\Filament\Commercial\Resources;

use App\Filament\Commercial\Resources\MisSupervisionesResource\Pages;
use App\Filament\Commercial\Resources\MisSupervisionesResource\RelationManagers\AusenciasRelationManager;
use App\Models\Note;
use App\Models\Supervision;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Colors\Color;
use App\Enums\NoteStatus;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Forms;
use App\Models\PostalCode;
use App\Enums\FuenteNotas;
use App\Enums\HorarioNotas;
use Filament\Forms\Form;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Team;
use Filament\Forms\Get;

class MisSupervisionesResource extends Resource
{
    protected static ?string $model = Note::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Mis Supervisiones';
    protected static ?string $pluralModelLabel = 'Mis Supervisiones';
    protected static ?string $modelLabel = 'Notas de mis supervisados';
    protected static ?int $navigationSort = 35;

    /** Mostrar en el menú solo si tengo supervisados vigentes hoy */
    public static function shouldRegisterNavigation(): bool
    {
        return count(self::getSupervisadoIdsVigentes()) > 0;
    }

    /** Si alguien intenta entrar por URL sin tener supervisados, negamos acceso */
    public static function canViewAny(): bool
    {
        return count(self::getSupervisadoIdsVigentes()) > 0;
    }

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
                        Forms\Components\Select::make('postal_code_id')
                            ->label('Código postal')
                            ->disabled()
                            ->options(function () {
                                return PostalCode::query()
                                    ->select('postal_codes.id', 'postal_codes.code', 'cities.title as city_title')
                                    ->join('cities', 'cities.id', '=', 'postal_codes.city_id')
                                    ->orderBy('cities.title')
                                    ->orderBy('postal_codes.code')
                                    ->limit(500)
                                    ->get()
                                    ->mapWithKeys(fn($item) => [
                                        $item->id => "{$item->code} - {$item->city_title}", // ← CP - Ciudad
                                    ]);
                            })
                            ->getOptionLabelUsing(function ($value) {
                                // Asegura el label correcto aunque no esté en options()
                                $pc = \App\Models\PostalCode::with('city')->find($value);
                                return $pc ? "{$pc->code} - {$pc->city->title}" : null;
                            })
                            ->searchable() // búsqueda en el desplegable
                            ->preload()
                            ->native(false)
                            ->validationMessages([
                                'required' => 'El código postal es obligatorio',
                            ]),

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
            ->paginated([20, 25, 50, 'all'])
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('nro_nota')
                    ->label('# Nota')
                    ->badge()
                    ->color(Color::Gray)
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('comercial.empleado_id')
                    ->label('ID Comercial')
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


                Tables\Columns\TextColumn::make('customer.postalCode.code')
                    ->label('CP'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(NoteStatus $state) => $state->getColor())
                    ->formatStateUsing(fn(NoteStatus $state) => $state->label())
                    ->sortable(),

                Tables\Columns\TextColumn::make('assignment_date')
                    ->label('Asignada')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('visit_schedule')
                    ->label('Horario')
                    ->badge()
                    ->color(Color::Gray)
                    ->sortable(),
            ])
            ->filters([
                // (opcional) agrega filtros por rango, estado, etc.
            ])
            ->actions([
                Tables\Actions\Action::make('assignCommercial')
                    ->label('')
                    ->icon('heroicon-s-user-plus')
                    ->form([
                        Forms\Components\Select::make('comercial_id')
                            ->label('Seleccionar Comercial')
                            ->options(function () {
                                $users = User::query()
                                    ->select('users.id', 'users.name', 'users.last_name', 'users.empleado_id')
                                    ->with(['roles:id,name'])
                                    ->role(['commercial', 'team_leader']) // cualquiera de los dos
                                    ->orderBy('empleado_id')
                                    ->get()
                                    ->unique('id');

                                $options = $users->mapWithKeys(function (User $user) {
                                    $hasTL = $user->roles->contains('name', 'team_leader');
                                    $hasCOM = $user->roles->contains('name', 'commercial');
                                    $tag = $hasTL && $hasCOM ? 'TL/COM' : ($hasTL ? 'TL' : 'COM');

                                    return [
                                        $user->id => "{$user->empleado_id} {$user->name} {$user->last_name} ({$tag})",
                                    ];
                                })->toArray();

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
                                : 'Comercial asignado correctamente: ' . User::find($data['comercial_id'])->name;

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
                // sin acciones masivas
            ]);
    }

    /** Query: solo notas cuyos comercial_id son mis supervisados vigentes hoy */
    public static function getEloquentQuery(): Builder
    {
        $supervisados = self::getSupervisadoIdsVigentes();

        // Si no hay ninguno, devolvemos vacío
        if (empty($supervisados)) {
            return parent::getEloquentQuery()->whereRaw('1=0');
        }

        return parent::getEloquentQuery()
            ->whereIn('comercial_id', $supervisados);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMisSupervisiones::route('/'),
            'edit' => Pages\EditMisSupervisiones::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            AusenciasRelationManager::class,
        ];
    }

    /**
     * Devuelve los IDs de usuarios supervisados por mí que estén vigentes hoy.
     * Vigente: start_date <= hoy AND (end_date IS NULL OR end_date >= hoy)
     */
    protected static function getSupervisadoIdsVigentes(): array
    {
        $userId = auth()->id();
        if (!$userId) {
            return [];
        }

        $hoy = now()->toDateString();

        return Supervision::query()
            ->where('supervisor_id', $userId)
            ->whereDate('start_date', '<=', $hoy)
            ->where(function ($q) use ($hoy) {
                $q->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $hoy);
            })
            ->pluck('supervisado_id')
            ->unique()
            ->values()
            ->all();
    }

    public static function getSupervisadosVigentes(): \Illuminate\Support\Collection
    {
        $ids = self::getSupervisadoIdsVigentes();
        if (empty($ids))
            return collect();

        return User::query()
            ->select('id', 'empleado_id', 'name')
            ->whereIn('id', $ids)
            ->orderBy('empleado_id')
            ->get();
    }

    /** No se crean notas desde aquí */
    public static function canCreate(): bool
    {
        return false;
    }
}
