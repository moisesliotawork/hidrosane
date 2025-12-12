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
use App\Models\NoteSalaEvent;
use Illuminate\Support\Collection;
use App\Models\User;
use Illuminate\Support\Facades\DB;


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
                            ->maxLength(20)
                            ->label('No. y Piso'),

                        Forms\Components\TextInput::make('ciudad')
                            ->required()
                            ->maxLength(255)
                            ->label('Ayuntamiento/Localidad'),

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
                                        $userId = auth()->id();

                                        return $record->anotacionesVisitas
                                            // FILTRO: solo autor = null o autor = usuario en sesión
                                            ->filter(function ($anotacion) use ($userId) {
                                                return is_null($anotacion->autor_id) || $anotacion->autor_id == $userId;
                                            })

                                            // Mapeo de formato (solo los filtrados)
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
                            ->relationship(
                                'observations',
                                fn($query) => $query->where(function ($q) {
                                    $userId = auth()->id();
                                    $q->whereNull('author_id')
                                        ->orWhere('author_id', $userId);
                                })
                            )
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
                                    ? User::find($state['author_id'])
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
                    ->formatStateUsing(function ($state) {
                        if (blank($state) || $state === '—') {
                            return '—';
                        }

                        // Siempre convertir a string antes de usarlo
                        $state = (string) $state;

                        $digits = preg_replace('/\s+/', '', $state);
                        return trim(chunk_split($digits, 3, ' '));
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
                    ->formatStateUsing(function ($state): string {
                        if (blank($state) || $state === '—') {
                            return '—';
                        }

                        // Inserta salto de línea cada 100 caracteres
                        return wordwrap((string) $state, 100, "\n", true);
                    })
                    ->extraAttributes([
                        'class' => 'whitespace-pre-wrap break-words',
                    ])
                    ->tooltip(fn(string $state) => $state ?: null)
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
                                $users = User::query()
                                    ->select('users.id', 'users.name', 'users.last_name', 'users.empleado_id')
                                    ->with(['roles:id,name'])
                                    ->role(['commercial', 'team_leader', 'sales_manager'])
                                    ->orderBy('empleado_id')
                                    ->get()
                                    ->unique('id');

                                $options = $users->mapWithKeys(function (User $user) {
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
                Tables\Actions\BulkAction::make('enviarAReten')
                    ->label('Enviar a RETEN')
                    ->icon('heroicon-s-lock-closed')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {

                        // Comercial RETEN fijo (id = 57)
                        $retenCommercial = User::find(57);

                        if (!$retenCommercial) {
                            Notification::make()
                                ->title('Error: Comercial RETEN no encontrado')
                                ->body('No existe un usuario con id = 57.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $now = now();
                        $cutoff = $now->copy()->subDays(5);

                        $totalEnviadas = 0;
                        $asignadasAReten = 0;
                        $reasignadasPorAntiguedad = 0;

                        // Para el mensaje: desglose por comercial (opcional)
                        $reasignadasPorComercial = []; // [comercial_id => count]
            
                        DB::transaction(function () use ($records, $now, $cutoff, $retenCommercial, &$totalEnviadas, &$asignadasAReten, &$reasignadasPorAntiguedad, &$reasignadasPorComercial) {

                            /** @var Note $note */
                            foreach ($records as $note) {

                                // 1) Si NO tiene comercial → asignar RETEN + fecha
                                if (is_null($note->comercial_id)) {
                                    $note->comercial_id = $retenCommercial->id;
                                    $note->assignment_date = $now;
                                    $asignadasAReten++;
                                } else {
                                    // 2) Si SÍ tiene comercial, y assignment_date existe y es >5 días vieja → reasignar al MISMO comercial
                                    // (en la práctica: refrescar assignment_date)
                                    if (!is_null($note->assignment_date) && $note->assignment_date->lt($cutoff)) {
                                        $note->assignment_date = $now;
                                        $reasignadasPorAntiguedad++;

                                        $reasignadasPorComercial[$note->comercial_id] = ($reasignadasPorComercial[$note->comercial_id] ?? 0) + 1;
                                    }
                                }

                                // 3) En todos los casos: enviar a RETEN
                                $note->reten = true;
                                $note->save();

                                $totalEnviadas++;
                            }
                        });

                        $displayReten = $retenCommercial->display_name ?? ($retenCommercial->empleado_id . ' ' . $retenCommercial->name . ' ' . $retenCommercial->last_name);

                        $bodyLines = [
                            "Total de notas enviadas a RETEN: {$totalEnviadas}",
                            "Notas sin comercial asignadas ahora a {$displayReten}: {$asignadasAReten}",
                            "Notas reasignadas al mismo comercial por antigüedad (> 5 días): {$reasignadasPorAntiguedad}",
                        ];

                        // Desglose (opcional, pero cumple “notificando que se reasigno ... al comercial tal...”)
                        if (!empty($reasignadasPorComercial)) {
                            $comerciales = User::whereIn('id', array_keys($reasignadasPorComercial))
                                ->get()
                                ->keyBy('id');

                            $detalle = collect($reasignadasPorComercial)
                                ->map(function ($count, $comId) use ($comerciales) {
                                    $u = $comerciales->get($comId);
                                    $name = $u
                                        ? ($u->display_name ?? ($u->empleado_id . ' ' . $u->name . ' ' . $u->last_name))
                                        : "Comercial #{$comId}";

                                    return "{$name}: {$count}";
                                })
                                ->values()
                                ->implode("\n");

                            $bodyLines[] = "Reasignadas por comercial:\n" . $detalle;
                        }

                        Notification::make()
                            ->title('Notas enviadas a RETEN')
                            ->body(implode("\n", $bodyLines))
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),

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

        // Por seguridad: si alguien sin rol válido llegara aquí, no ve nada
        if (!$user || !($user->hasRole('sales_manager') || $user->hasRole('team_leader'))) {
            // Opcional: devolver query vacía
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        // Rango de fecha: desde hoy-5 hasta hoy (INCLUSIVO)
        $desde = now()->subDays(5)->toDateString();
        $hasta = now()->toDateString();

        $query = parent::getEloquentQuery();

        // Estado terminal: null, '', o 'ausente'
        $query->where(function ($q) {
            $q->whereNull('estado_terminal')
                ->orWhere('estado_terminal', '')
                ->orWhereRaw("LOWER(TRIM(estado_terminal)) = 'ausente'");
        })
            // Sin venta asociada
            ->whereDoesntHave('venta')
            // Solo notas marcadas como Reten
            ->where('reten', true)
            // Assignment_date entre hoy-5 y hoy
            ->whereBetween(\DB::raw('DATE(assignment_date)'), [$desde, $hasta]);

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