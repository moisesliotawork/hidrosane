<?php

namespace App\Filament\HeadOfRoom\Resources;

use App\Filament\HeadOfRoom\Resources\NoteResource\Pages;
use App\Filament\HeadOfRoom\Resources\NoteResource\RelationManagers;
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
use App\Enums\EstadoTerminal;
use Filament\Notifications\Notification;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Filament\Support\Colors\Color;
use Illuminate\Support\Collection;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Enums\FiltersLayout;
use App\Models\User;
use App\Models\NoteReassignmentBatch;
use App\Models\NoteReassignmentLog;
use Illuminate\Validation\Rule;
use App\Models\Customer;
use App\Models\Venta;
use Filament\Notifications\Actions\Action as NotificationAction;
use App\Filament\HeadOfRoom\Pages\BuscarCliente;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\ToggleColumn;

class NoteResource extends Resource
{
    protected static ?string $model = Note::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Notas';

    protected static ?string $modelLabel = 'Nota';

    protected static ?string $pluralModelLabel = 'Notas';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->where(function (Builder $q) {
                $q->whereNull('estado_terminal')
                    ->orWhereIn('estado_terminal', [
                        EstadoTerminal::SIN_ESTADO->value,  // ''
                        EstadoTerminal::SALA->value,        // 'sala'
                    ]);
            });
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('customer_id'),
                Forms\Components\Hidden::make('comercial_id'),

                Forms\Components\Section::make('Teleoperadora')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Teleoperadora asignada')
                            ->native(false)
                            ->preload()
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search): array {
                                return User::query()
                                    ->role(['teleoperator', 'head_of_room'])
                                    ->where(function (Builder $q) use ($search) {
                                        $q->where('name', 'like', "%{$search}%")
                                            ->orWhere('last_name', 'like', "%{$search}%")
                                            ->orWhere('empleado_id', 'like', "%{$search}%");
                                    })
                                    ->orderBy('empleado_id')
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn($u) => [$u->id => $u->display_name])
                                    ->toArray();
                            })
                            ->options(function (): array {
                                return User::query()
                                    ->role(['teleoperator', 'head_of_room'])
                                    ->orderBy('empleado_id')
                                    ->get()
                                    ->mapWithKeys(fn($u) => [$u->id => $u->display_name])
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(
                                fn($value): ?string => $value
                                    ? User::find($value)?->display_name
                                    : null
                            )
                            ->required(),
                    ])
                    ->columns(1),



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
                            ->label('Teléfono')
                            ->mask('999 999 999') // se ve con espacios
                            // Validación: exactamente 9 dígitos (ignora espacios/guiones)
                            ->rule(function () {
                                return function (string $attribute, $value, \Closure $fail) {
                                    $digits = preg_replace('/\D+/', '', (string) $value);
                                    if (strlen($digits) !== 9) {
                                        $fail('Debe tener exactamente 9 cifras');
                                    }
                                };
                            })
                            // Guardar: solo dígitos
                            ->dehydrateStateUsing(fn($state) => preg_replace('/\D+/', '', (string) $state))
                            ->dehydrated(true),

                        Forms\Components\TextInput::make('secondary_phone')
                            ->tel()
                            ->label('Teléfono secundario (opcional)')
                            ->mask('999 999 999')
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

                        Forms\Components\TextInput::make('third_phone')
                            ->tel()
                            ->label('Teléfono 3 (opcional)')
                            ->mask('999 999 999')
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

                        Forms\Components\TextInput::make('secondary_address')
                            ->maxLength(255)
                            ->label('Dirección secundaria (opcional)'),

                        Forms\Components\TextInput::make('nro_piso')
                            ->required()
                            ->maxLength(20)
                            ->label('No. y Piso'),

                        Forms\Components\TextInput::make('ciudad')
                            ->required()
                            ->maxLength(255)
                            ->label('Ayuntamiento/Localidad'),

                        Forms\Components\TextInput::make('postal_code')
                            ->required()
                            ->maxLength(20)
                            ->label('Codigo Postal'),

                        Forms\Components\TextInput::make('provincia')
                            ->required()
                            ->maxLength(255)
                            ->label('Provincia'),

                        Forms\Components\TextInput::make('parish')
                            ->maxLength(255)
                            ->label('Parroquia (opcional)'),

                    ])->columns(2),

                Forms\Components\Section::make('Gestión Comercial')
                    ->schema([

                        Forms\Components\Select::make('fuente')
                            ->label('Tipo')
                            ->native(false)
                            ->options(\App\Enums\FuenteNotas::options()) // sin filtrar opciones
                            // === visibilidad y comportamiento ===
                            ->hidden(fn() => !auth()->user()?->canSeeVipSources())
                            ->dehydrated(fn() => auth()->user()?->canSeeVipSources())   // solo envía el valor si se muestra
                            ->required(fn() => auth()->user()?->canSeeVipSources()),     // solo es requerido si se muestra



                        Forms\Components\Select::make('status')
                            ->options(NoteStatus::options())
                            ->required()
                            ->native(false)
                            ->live()
                            ->default(NoteStatus::CONTACTED->value)
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
                            ->schema([
                                Forms\Components\Hidden::make('id'),
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
                            ->dehydrated(true)
                            ->columnSpanFull()
                            ->itemLabel(function (array $state): ?string {
                                // Usar el usuario autenticado como fallback
                                $author = auth()->user();

                                // Si hay un author_id en el estado, intentar cargar el usuario
                                if (isset($state['author_id'])) {
                                    $author = User::find($state['author_id']) ?? $author;
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
                Forms\Components\Section::make('Observaciones en Sala')
                    ->visible(function (?Note $record) {
                        if (!$record)
                            return false;

                        // Enum o string, ambos casos:
                        $isEnum = $record->estado_terminal instanceof \App\Enums\EstadoTerminal;
                        return $isEnum
                            ? $record->estado_terminal === \App\Enums\EstadoTerminal::SALA
                            : (string) $record->estado_terminal === \App\Enums\EstadoTerminal::SALA->value;
                    })
                    ->schema([
                        Forms\Components\Placeholder::make('sala_observations_list')
                            ->label('')
                            ->content(function (?Note $record) {
                                if (!$record) {
                                    return new HtmlString('<em>—</em>');
                                }

                                $rows = $record->observacionesSala()
                                    ->with('author')
                                    ->orderByDesc('created_at')
                                    ->get();

                                if ($rows->isEmpty()) {
                                    return new HtmlString('<em>Sin observaciones de sala.</em>');
                                }

                                $items = $rows->map(function ($r) {
                                    $fecha = optional($r->created_at)->format('d/m/Y H:i') ?? '—';
                                    $autor = e(optional($r->author)->name ?? '—');
                                    $txt = e((string) ($r->observation ?? ''));
                                    return "<li><strong>{$fecha}</strong> · {$autor}: {$txt}</li>";
                                })->implode('');

                                return new HtmlString("<ul style='margin:0;padding-left:1.25rem'>{$items}</ul>");
                            }),
                    ]),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->recordAction(null)
            ->paginated([20, 25, 30, 40, 'all'])
            ->columns([

                Tables\Columns\TextColumn::make('nro_nota')
                    ->searchable()
                    ->label('Nº Nota')
                    ->badge()
                    ->color('warning')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('estado_terminal')
                    ->label('En nota')
                    ->badge()
                    ->formatStateUsing(fn (Note $record): string => $record->estado_terminal->enNotaLabel())
                    ->color(fn (Note $record): string => $record->estado_terminal->enNotaColor())
                    ->tooltip('Clic para cambiar el estado')
                    ->alignCenter()
                    ->action(function (Note $record): void {
                        $next = EstadoTerminal::nextFromRaw($record->getRawOriginal('estado_terminal'));

                        $record->update(['estado_terminal' => $next->value]);

                        Notification::make()
                            ->title('Estado actualizado')
                            ->body("En nota: {$next->enNotaLabel()}")
                            ->success()
                            ->send();
                    }),

                // Tables\Columns\TextColumn::make('fuente')
                // ->badge()
                // ->color(fn(FuenteNotas $state): string => $state->getColor())
                // ->formatStateUsing(fn(FuenteNotas $state): string => $state->getPuntaje() . ' pts')
                // ->label('Puntos'),

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
                    ->searchable()
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
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Estado'),

                Tables\Columns\TextColumn::make('comercial_empleado')
                    ->label('Com.')
                    ->badge()
                    ->color(function ($state) {
                        if ($state === 'Sin Com.') {
                            return 'gray';
                        }
                        if ($state === 'Comercial no encontrado') {
                            return 'danger';
                        }
                        return 'success';
                    }),

                Tables\Columns\TextColumn::make('assignment_date')
                    ->label('Asig.')
                    ->date("d/m/Y")
                    ->toggleable()
                    ->toggledHiddenByDefault(false)
                    ->sortable(),

                Tables\Columns\TextColumn::make('visit_schedule')
                    ->badge()
                    ->color(Color::Gray)
                    ->label('Horario')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('sent_to_sala_at')
                    ->label('Fecha Of.')
                    ->state(function (Note $record) {
                        // Solo mostrar fecha si el TN es SALA
                        return $record->estado_terminal === EstadoTerminal::SALA
                            ? $record->sent_to_sala_at
                            : null;
                    })
                    ->dateTime('d/m/Y H:i')   // formato dd/mm/yyyy hh:mm
                    ->placeholder('—')        // guion si está vacío
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: false), // cámbialo a true si quieres ocultarla por defecto

                Tables\Columns\TextColumn::make('printed')
                    ->label('Impr.')
                    ->badge()
                    ->formatStateUsing(fn(bool $state) => $state ? 'IMPRESO' : 'NO IMPRESO')
                    ->color(fn(bool $state) => $state ? 'gray' : 'warning')
                    ->alignCenter()
                    ->sortable()
                    ->action(function (Note $record) { // <--- USAR ESTO PARA CAMBIAR EL ESTADO
                        $record->update([
                            'printed' => !$record->printed
                        ]);
                    }),


                Tables\Columns\TextColumn::make('reten')
                    ->label('Reten')
                    ->badge()
                    ->formatStateUsing(fn(?bool $state) => $state ? 'SI' : 'NO')
                    ->color(fn(?bool $state) => $state ? 'success' : 'danger')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fech/Nota')
                    ->badge()
                    ->color('success')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('assignment_from')
                    ->label('Desde')
                    ->form([
                        Forms\Components\DatePicker::make('start')
                            ->label('Desde')
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->timezone('Europe/Madrid'),
                    ])
                    ->query(fn(Builder $query, array $data): Builder =>
                        $query->when(
                            $data['start'] ?? null,
                            fn(Builder $q, $d) => $q->whereDate('assignment_date', '>=', $d)
                        )
                    )
                    ->indicateUsing(fn(array $data): ?string =>
                        ($data['start'] ?? null) ? 'Asig. desde: ' . Carbon::parse($data['start'])->format('d/m/Y') : null
                    ),

                Tables\Filters\Filter::make('assignment_to')
                    ->label('Hasta')
                    ->form([
                        Forms\Components\DatePicker::make('end')
                            ->label('Hasta')
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->timezone('Europe/Madrid'),
                    ])
                    ->query(fn(Builder $query, array $data): Builder =>
                        $query->when(
                            $data['end'] ?? null,
                            fn(Builder $q, $d) => $q->whereDate('assignment_date', '<=', $d)
                        )
                    )
                    ->indicateUsing(fn(array $data): ?string =>
                        ($data['end'] ?? null) ? 'Asig. hasta: ' . Carbon::parse($data['end'])->format('d/m/Y') : null
                    ),

                Tables\Filters\Filter::make('assignment_date')
                    ->form([
                        Forms\Components\DatePicker::make('assignment_date')
                            ->label('Fecha exacta de asignación')
                            ->displayFormat('d/m/Y')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['assignment_date'],
                                fn(Builder $query, $date) => $query->whereDate('assignment_date', $date)
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (!$data['assignment_date']) {
                            return null;
                        }
                        return 'Fecha asig.: ' . Carbon::parse($data['assignment_date'])->format('d/m/Y');
                    }),

                Tables\Filters\SelectFilter::make('comercial_id')
                    ->label('Comercial')
                    ->options(function () {
                        return User::role(['commercial', 'team_leader', 'sales_manager'])
                            ->select('users.id', 'users.name', 'users.last_name', 'users.empleado_id')
                            ->orderBy('users.name')
                            ->distinct()
                            ->get()
                            ->mapWithKeys(fn($u) => [
                                $u->id => "{$u->empleado_id} {$u->name} {$u->last_name}",
                            ])
                            ->toArray();
                    })
                    ->searchable()
                    ->native(false),

                // Fila 2: Fecha Oficina | Impresas | Retén
                Tables\Filters\Filter::make('sent_to_sala_date')
                    ->label('Fecha Oficina')
                    ->form([
                        Forms\Components\DatePicker::make('sent_to_sala_at')
                            ->label('Fecha Oficina')
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->timezone('Europe/Madrid'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['sent_to_sala_at'] ?? null,
                                fn(Builder $q, $date) => $q
                                    ->whereDate('sent_to_sala_at', $date)
                                    ->where('estado_terminal', EstadoTerminal::SALA->value)
                            );
                    })
                    ->indicateUsing(fn(array $data) =>
                        ($data['sent_to_sala_at'] ?? null)
                            ? 'Oficina: ' . Carbon::parse($data['sent_to_sala_at'])->format('d/m/Y')
                            : null
                    ),

                TernaryFilter::make('printed')
                    ->label('Impresas')
                    ->trueLabel('Solo impresas')
                    ->falseLabel('Solo no impresas')
                    ->placeholder('Todas')
                    ->native(false)
                    ->queries(
                        true: fn(Builder $q) => $q->where('printed', true),
                        false: fn(Builder $q) => $q->where('printed', false),
                        blank: fn(Builder $q) => $q,
                    ),

                Tables\Filters\TernaryFilter::make('reten')
                    ->label('Retén')
                    ->trueLabel('Solo en Retén')
                    ->falseLabel('Solo fuera de Retén')
                    ->placeholder('Todas')
                    ->native(false)
                    ->queries(
                        true: fn(Builder $q) => $q->where('reten', true),
                        false: fn(Builder $q) => $q->where('reten', false),
                        blank: fn(Builder $q) => $q,
                    ),

            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label(''),
                Tables\Actions\DeleteAction::make()
                    ->label(''),

                Tables\Actions\Action::make('assignCommercial')
                    ->label('')
                    ->icon('heroicon-s-user-plus')
                    ->form(function (Note $record) {
                        return [
                            Forms\Components\Select::make('comercial_id')
                                ->label('Seleccionar Comercial')
                                ->options(function () use ($record) {
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

                                        $tag = $hasTL && $hasCOM && $hasJV
                                            ? 'TL/COM'
                                            : ($hasCOM ? 'COM' : 'TL');

                                        return [
                                            $user->id => "{$user->empleado_id} {$user->name} {$user->last_name} ({$tag})",
                                        ];
                                    })->toArray();

                                    // Opciones base (siempre)
                                    $baseOptions = [
                                            null => 'Sin asignar',
                                        ] + $options;

                                    // ⬇️ SOLO si la nota YA tiene comercial asignado mostramos COMERCIAL RETEN
                                    if (!is_null($record->comercial_id)) {
                                        $baseOptions = [
                                                '__RETEN__' => 'COMERCIAL RETEN',
                                            ] + $baseOptions;
                                    }

                                    return $baseOptions;
                                })
                                ->searchable()
                                ->native(false),

                            Forms\Components\DatePicker::make('assignment_date')
                                ->label('Fecha de asignación')
                                ->hint('Si se deja vacío, se usará la fecha actual')
                                ->required(false),
                        ];
                    })

                    ->action(function (Note $record, array $data): void {
                        try {
                            // Se mantiene tu lógica existente
                            if (($data['comercial_id'] ?? null) === '__RETEN__') {
                                $record->update(['reten' => true]);

                                Notification::make()
                                    ->title('Marcada como COMERCIAL RETEN')
                                    ->success()
                                    ->send();

                                return;
                            }

                            // ── Verificación venta reciente ────────────────────────────────
                            if (!is_null($data['comercial_id'] ?? null)) {
                                $customer = $record->customer;

                                if ($customer) {
                                    $phones = collect([
                                        $customer->phone,
                                        $customer->secondary_phone,
                                        $customer->third_phone,
                                        $customer->phone1_commercial,
                                        $customer->phone2_commercial,
                                    ])->filter()->values();

                                    $nameWords = collect(
                                        preg_split('/\s+/u', mb_strtolower(trim(
                                            ($customer->first_names ?? '') . ' ' . ($customer->last_names ?? '')
                                        )))
                                    )->filter(fn($w) => mb_strlen($w) > 2)->values();

                                    if ($phones->isNotEmpty() && $nameWords->isNotEmpty()) {
                                        $cutoff = now()->startOfMonth()->subMonthsNoOverflow(4);

                                        $matchingCustomer = Customer::query()
                                            ->where(function ($q) use ($nameWords) {
                                                foreach ($nameWords as $word) {
                                                    $q->orWhere(DB::raw('LOWER(first_names)'), 'like', "%{$word}%")
                                                      ->orWhere(DB::raw('LOWER(last_names)'), 'like', "%{$word}%");
                                                }
                                            })
                                            ->where(function ($q) use ($phones) {
                                                foreach ($phones as $phone) {
                                                    $q->orWhere('phone', $phone)
                                                      ->orWhere('secondary_phone', $phone)
                                                      ->orWhere('third_phone', $phone)
                                                      ->orWhere('phone1_commercial', $phone)
                                                      ->orWhere('phone2_commercial', $phone);
                                                }
                                            })
                                            ->whereHas('ventas', fn($q) => $q->where('fecha_venta', '>=', $cutoff))
                                            ->first();

                                        if ($matchingCustomer) {
                                            $recentVenta = Venta::where('customer_id', $matchingCustomer->id)
                                                ->where('fecha_venta', '>=', $cutoff)
                                                ->with('comercial:id,empleado_id')
                                                ->latest('fecha_venta')
                                                ->first();

                                            $clientName  = mb_strtoupper(trim(
                                                ($matchingCustomer->first_names ?? '') . ' ' . ($matchingCustomer->last_names ?? '')
                                            ));
                                            $fechaVenta  = $recentVenta
                                                ? Carbon::parse($recentVenta->fecha_venta)->format('d/m/Y H:i')
                                                : '—';
                                            $comercialId = $recentVenta?->comercial?->empleado_id ?? '—';

                                            Notification::make()
                                                ->title('⛔ Asignación bloqueada')
                                                ->body(
                                                    "NO PUEDES REASIGNAR AL CLIENTE: {$clientName}, "
                                                    . "tiene una venta reciente con fecha {$fechaVenta}, "
                                                    . "declarada por: {$comercialId}"
                                                )
                                                ->danger()
                                                ->persistent()
                                                ->send();

                                            return;
                                        }
                                    }
                                }
                            }
                            // ── Fin verificación ──────────────────────────────────────────

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
                    }),

            ])
            ->headerActions([
                Action::make('irABuscarCliente')
                    ->label('Crear Nota nueva          ')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('warning')
                    ->url(fn() => BuscarCliente::getUrl()),
                //Tables\Actions\Action::make('buscarTelefono')
                //    ->label('Buscar teléfono')
                //    ->icon('heroicon-o-magnifying-glass')
                //    ->color('orange')
                //    ->modalHeading('Buscar cliente por teléfono')
                //    ->modalSubmitActionLabel('Buscar')
                //    ->form([
                //        Forms\Components\TextInput::make('phone_query')
                //            ->label('INGRESA NUMERO DE TELEFONO')
                //            ->tel()
                //            ->required()
                //            ->mask('999 999 999')
                //            ->rule(function () {
                //                return function (string $attribute, $value, \Closure $fail) {
                //                    $digits = preg_replace('/\D+/', '', (string) $value);
                //                    if (strlen($digits) !== 9) {
                //                        $fail('Debe tener exactamente 9 cifras.');
                //                    }
                //                };
                //            }),
                //    ])
                //    ->action(function (array $data) {
                //        $digits = preg_replace('/\D+/', '', (string) ($data['phone_query'] ?? ''));
//
                //        if (strlen($digits) !== 9) {
                //            Notification::make()
                //                ->title('Teléfono inválido')
                //                ->body('Debe tener exactamente 9 cifras.')
                //                ->danger()
                //                ->send();
//
                //            return;
                //        }
//
                //        // Buscar el cliente por los distintos teléfonos
                //        $customer = Customer::query()
                //            ->where('phone', $digits)
                //            ->orWhere('secondary_phone', $digits)
                //            ->orWhere('third_phone', $digits)
                //            ->first();
//
                //        // Si NO existe → flujo actual: crear nota nueva con el phone
                //        if (!$customer) {
                //            $url = static::getUrl('create', ['phone' => $digits]);
//
                //            return redirect($url);
                //        }
//
                //        // Si SÍ existe → mostrar resumen + botones
                //
                //        $fullName = trim(($customer->first_names ?? '') . ' ' . ($customer->last_names ?? ''));
                //        $dni = $customer->dni ?? '—';
//
                //        $notesCount = Note::where('customer_id', $customer->id)->count();
                //        $ventasCount = Venta::where('customer_id', $customer->id)->count();
//
                //        $bodyLines = [
                //            "Nombre: {$fullName}",
                //            "DNI: {$dni}",
                //            "Notas asociadas: {$notesCount}",
                //            "Ventas asociadas: {$ventasCount}",
                //        ];
//
                //        Notification::make()
                //            ->title('CLIENTE ENCONTRADO')
                //            ->body(implode("\n", $bodyLines))
                //            ->info()
                //            ->persistent() // se queda hasta que el usuario interactúe
                //            ->actions([
                //                NotificationAction::make('no_continuar')
                //                    ->label('No continuar')
                //                    ->button()
                //                    ->color('gray')
                //                    ->close(),
//
//
                //                NotificationAction::make('continuar')
                //                    ->label('Continuar')
                //                    ->button()
                //                    ->color('success')
                //                    ->url(static::getUrl('create', [
                //                        'customer_id' => $customer->id,
                //                    ]))
                //                    ->openUrlInNewTab(false),
                //            ])
                //            ->send();
                //    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('pdfSalaSoloNoImpresas')
                    ->label('PDF - Oficina')
                    ->icon('heroicon-o-printer')
                    ->color('pink')
                    ->requiresConfirmation()
                    ->action(function (Collection $records) {
                        // 1) Solo no impresas
                        $idsNoImpresas = $records
                            ->filter(fn(Note $n) => !$n->printed)
                            ->pluck('id')
                            ->values()
                            ->all();

                        if (empty($idsNoImpresas)) {
                            Notification::make()
                                ->title('Todas las seleccionadas ya estaban IMPRESAS')
                                ->body('No se generó ningún PDF.')
                                ->warning()
                                ->send();
                            return;
                        }

                        // 2) Validar TN permitido
                        $validIds = Note::query()
                            ->whereIn('id', $idsNoImpresas)
                            ->where(function (Builder $q) {
                                $q->whereNull('estado_terminal')
                                    ->orWhereIn('estado_terminal', [
                                        EstadoTerminal::SIN_ESTADO->value,
                                        EstadoTerminal::SALA->value,
                                    ]);
                            })
                            ->pluck('id')
                            ->all();

                        if (empty($validIds)) {
                            Notification::make()
                                ->title('No hay notas válidas por TN')
                                ->body('Solo se permiten SIN_ESTADO o SALA.')
                                ->warning()
                                ->send();
                            return;
                        }

                        // 3) Marcar impresas antes del PDF
                        DB::transaction(function () use ($validIds) {
                            Note::whereIn('id', $validIds)->update([
                                'printed' => 1,
                                'updated_at' => now(),
                            ]);
                        });

                        // 4) Cargar datos y generar PDF
                        $notes = Note::query()
                            ->whereIn('id', $validIds)
                            ->with([
                                'user',
                                'comercial',
                                'observations.author',
                                'observacionesSala.author',
                            ])
                            ->orderBy('nro_nota')
                            ->get();

                        if ($notes->isEmpty()) {
                            Notification::make()
                                ->title('No hay notas para renderizar en el PDF')
                                ->warning()
                                ->send();
                            return;
                        }

                        $pdf = Pdf::loadView('pdf.notas-sala', ['notes' => $notes])->setPaper('a4');
                        return response()->streamDownload(
                            fn() => print ($pdf->output()),
                            'notas-oficina-' . now()->format('Ymd-His') . '.pdf'
                        );
                    })
                    ->deselectRecordsAfterCompletion(),

                Tables\Actions\DeleteBulkAction::make()
                    ->label('Eliminar Selec.')
                    ->modalHeading('Eliminar notas seleccionadas')
                    ->modalDescription('¿Estás seguro de que quieres eliminar las notas seleccionadas? Esta acción no se puede deshacer.')
                    ->modalSubmitActionLabel('Sí, eliminar')
                    ->successNotificationTitle('Notas eliminadas correctamente'),
                Tables\Actions\BulkAction::make('assignCommercialBulk')
                    ->label('Asig. Com.')
                    ->icon('heroicon-s-user-plus')
                    ->form([
                        Forms\Components\Select::make('comercial_id')
                            ->label('Seleccionar Comercial')
                            ->options(function () {
                                return User::role(['commercial', 'team_leader', 'sales_manager'])
                                    ->whereNull('baja') // <-- SOLO activos (ajusta a fecha_baja si así se llama)
                                    ->orderBy('name')
                                    ->select('id', 'name', 'last_name', 'empleado_id')
                                    ->get()
                                    ->mapWithKeys(fn($u) => [
                                        $u->id => "{$u->empleado_id} {$u->name} {$u->last_name}",
                                    ])
                                    ->toArray();
                            })
                            ->searchable()
                            ->native(false)
                            ->placeholder('Sin asignar') // null
                            ->rules([
                                'nullable',
                                'integer',
                                Rule::exists('users', 'id')->where(function ($q) {
                                    $q->whereNull('baja') // <-- activos
                                    ->whereExists(function ($sq) {
                                        $sq->selectRaw(1)
                                            ->from('model_has_roles as mhr')
                                            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
                                            ->whereColumn('mhr.model_id', 'users.id')
                                            ->where('mhr.model_type', User::class)
                                            ->whereIn('r.name', ['commercial', 'team_leader', 'sales_manager']);
                                    });
                                }),
                            ]),
                        Forms\Components\DatePicker::make('assignment_date')
                            ->label('Fecha de asignación')
                            ->hint('Si se deja vacío, se usará la fecha actual')
                            ->required(false),
                    ])
                    ->action(function (iterable $records, array $data): void {
                        try {
                            $comercialId = $data['comercial_id'] ?? null;

                            // Doble verificación en runtime
                            if (!empty($comercialId)) {
                                $isValid = User::query()
                                    ->where('id', $comercialId)
                                    ->whereNull('baja')
                                    ->whereHas('roles', fn($r) => $r->whereIn('name', ['commercial', 'team_leader', 'sales_manager']))
                                    ->exists();

                                if (!$isValid) {
                                    throw new \RuntimeException('El comercial seleccionado no está activo o no tiene un rol válido.');
                                }
                            }

                            $assignmentDate = !empty($comercialId)
                                ? ($data['assignment_date'] ?? now())
                                : null;

                            $allRecords  = collect($records);
                            $blockedItems = collect();
                            $cleanIds    = [];
                            $toResetIds  = [];

                            // ── Verificar ventas recientes (solo al asignar) ───────────────
                            if (!empty($comercialId)) {
                                $cutoff = now()->startOfMonth()->subMonthsNoOverflow(4);

                                foreach ($allRecords as $note) {
                                    $customer  = $note->customer;
                                    $isBlocked = false;

                                    if ($customer) {
                                        $phones = collect([
                                            $customer->phone,
                                            $customer->secondary_phone,
                                            $customer->third_phone,
                                            $customer->phone1_commercial,
                                            $customer->phone2_commercial,
                                        ])->filter()->values();

                                        $nameWords = collect(
                                            preg_split('/\s+/u', mb_strtolower(trim(
                                                ($customer->first_names ?? '') . ' ' . ($customer->last_names ?? '')
                                            )))
                                        )->filter(fn($w) => mb_strlen($w) > 2)->values();

                                        if ($phones->isNotEmpty() && $nameWords->isNotEmpty()) {
                                            $matchingCustomer = Customer::query()
                                                ->where(function ($q) use ($nameWords) {
                                                    foreach ($nameWords as $word) {
                                                        $q->orWhere(DB::raw('LOWER(first_names)'), 'like', "%{$word}%")
                                                          ->orWhere(DB::raw('LOWER(last_names)'), 'like', "%{$word}%");
                                                    }
                                                })
                                                ->where(function ($q) use ($phones) {
                                                    foreach ($phones as $phone) {
                                                        $q->orWhere('phone', $phone)
                                                          ->orWhere('secondary_phone', $phone)
                                                          ->orWhere('third_phone', $phone)
                                                          ->orWhere('phone1_commercial', $phone)
                                                          ->orWhere('phone2_commercial', $phone);
                                                    }
                                                })
                                                ->whereHas('ventas', fn($q) => $q->where('fecha_venta', '>=', $cutoff))
                                                ->first();

                                            if ($matchingCustomer) {
                                                $recentVenta = Venta::where('customer_id', $matchingCustomer->id)
                                                    ->where('fecha_venta', '>=', $cutoff)
                                                    ->with('comercial:id,empleado_id')
                                                    ->latest('fecha_venta')
                                                    ->first();

                                                $blockedItems->push([
                                                    'note_id'       => $note->id,
                                                    'nro_nota'      => $note->nro_nota,
                                                    'customer_name' => mb_strtoupper(trim(
                                                        ($matchingCustomer->first_names ?? '') . ' ' . ($matchingCustomer->last_names ?? '')
                                                    )),
                                                    'fecha_venta'   => $recentVenta
                                                        ? Carbon::parse($recentVenta->fecha_venta)->format('d/m/Y H:i')
                                                        : '—',
                                                    'comercial_emp' => $recentVenta?->comercial?->empleado_id ?? '—',
                                                ]);
                                                $isBlocked = true;
                                            }
                                        }
                                    }

                                    if (!$isBlocked) {
                                        $cleanIds[] = $note->id;
                                    }
                                }
                            } else {
                                // Desasignación: procesar todas
                                $cleanIds = $allRecords->pluck('id')->all();
                            }
                            // ── Fin verificación ─────────────────────────────────────────

                            // 1) Asignar las notas limpias
                            if (!empty($cleanIds)) {
                                Note::whereIn('id', $cleanIds)->update([
                                    'comercial_id'    => (!empty($comercialId) ? $comercialId : null),
                                    'assignment_date' => $assignmentDate,
                                ]);

                                // 2) Resetear TN a S/E para las que estén en SALA
                                $toResetIds = Note::whereIn('id', $cleanIds)
                                    ->where('estado_terminal', EstadoTerminal::SALA->value)
                                    ->pluck('id')
                                    ->all();

                                if (!empty($toResetIds)) {
                                    Note::whereIn('id', $toResetIds)->update([
                                        'estado_terminal' => EstadoTerminal::SIN_ESTADO->value,
                                        'sent_to_sala_at' => null,
                                    ]);
                                }
                            }

                            // Sin bloqueadas → notificación normal
                            if ($blockedItems->isEmpty()) {
                                Notification::make()
                                    ->title('Asignación masiva completada')
                                    ->body(
                                        (empty($comercialId) ? 'Comercial removido' : 'Comercial asignado')
                                        . (!empty($toResetIds) ? ' • TN reiniciado en ' . count($toResetIds) . ' nota(s)' : '')
                                    )
                                    ->success()
                                    ->send();
                                return;
                            }

                            // Guardar IDs bloqueados en sesión
                            $blockedNoteIds = $blockedItems->pluck('note_id')->all();
                            session()->put('hor_blocked_note_ids', $blockedNoteIds);

                            // Construir tabla HTML
                            $rows = $blockedItems->map(fn($b) =>
                                "<tr style='border-bottom:1px solid #374151;'>"
                                . "<td style='padding:4px 8px;color:#fca5a5;font-weight:700;'>{$b['nro_nota']}</td>"
                                . "<td style='padding:4px 8px;color:#fff;font-weight:600;'>{$b['customer_name']}</td>"
                                . "<td style='padding:4px 8px;color:#fcd34d;'>{$b['fecha_venta']}</td>"
                                . "<td style='padding:4px 8px;color:#6ee7b7;'>ID: {$b['comercial_emp']}</td>"
                                . "</tr>"
                            )->join('');

                            $cleanMsg = !empty($cleanIds)
                                ? '<p style="margin-top:10px;color:#6ee7b7;font-weight:600;">✅ ' . count($cleanIds) . ' nota(s) asignadas correctamente.</p>'
                                : '';

                            $bodyHtml =
                                '<div style="font-size:13px;">'
                                . '<p style="margin-bottom:8px;color:#fca5a5;font-weight:600;">Clientes con venta en los últimos 4 meses:</p>'
                                . '<table style="width:100%;border-collapse:collapse;">'
                                . '<thead><tr style="background:#1f2937;">'
                                . '<th style="padding:4px 8px;text-align:left;color:#9ca3af;font-size:11px;text-transform:uppercase;">Nota</th>'
                                . '<th style="padding:4px 8px;text-align:left;color:#9ca3af;font-size:11px;text-transform:uppercase;">Cliente</th>'
                                . '<th style="padding:4px 8px;text-align:left;color:#9ca3af;font-size:11px;text-transform:uppercase;">Fecha Venta</th>'
                                . '<th style="padding:4px 8px;text-align:left;color:#9ca3af;font-size:11px;text-transform:uppercase;">Declarada por</th>'
                                . '</tr></thead>'
                                . "<tbody>{$rows}</tbody>"
                                . '</table>'
                                . $cleanMsg
                                . '</div>';

                            Notification::make()
                                ->title('⚠️ ' . $blockedItems->count() . ' nota(s) con ventas recientes detectadas')
                                ->body(new HtmlString($bodyHtml))
                                ->danger()
                                ->persistent()
                                ->actions([
                                    NotificationAction::make('delete_blocked')
                                        ->label('ELIMINAR NOTAS CON VENTAS RECIENTES')
                                        ->color('danger')
                                        ->action(function () {
                                            $ids = session()->pull('hor_blocked_note_ids', []);
                                            if (!empty($ids)) {
                                                Note::whereIn('id', $ids)->delete();
                                            }
                                            Notification::make()
                                                ->title('✅ Notas eliminadas: ' . count($ids))
                                                ->success()
                                                ->send();
                                        }),
                                    NotificationAction::make('cerrar')
                                        ->label('Salir')
                                        ->close(),
                                ])
                                ->send();

                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Error en asignación masiva')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->deselectRecordsAfterCompletion(),
                //Tables\Actions\BulkAction::make('enviarAReten')
                //    ->label('Enviar a RETEN')
                //    ->icon('heroicon-s-lock-closed')
                //    ->color('warning')
                //    ->requiresConfirmation()
                //    ->action(function (Collection $records): void {
//
                //        // Comercial RETEN fijo (id = 57 Alba JEFE DE VENTAS)
                //        $retenCommercial = User::find(57);
//
                //        if (!$retenCommercial) {
                //            Notification::make()
                //                ->title('Error: Comercial RETEN no encontrado')
                //                ->body('No existe un usuario con id = 1.')
                //                ->danger()
                //                ->send();
//
                //            return;
                //        }
//
                //        $now = now();
                //        $totalEnviadas = 0;
                //        $asignadasAReten = 0;
//
                //        DB::transaction(function () use ($records, $now, $retenCommercial, &$totalEnviadas, &$asignadasAReten) {
//
                //            /** @var Note $note */
                //            foreach ($records as $note) {
//
                //                // Si la nota no tiene comercial, la asignamos al comercial RETEN
                //                if (is_null($note->comercial_id)) {
                //                    $note->comercial_id = $retenCommercial->id;
                //                    $note->assignment_date = $now;
                //                    $asignadasAReten++;
                //                }
//
                //                if ($note->estado_terminal === EstadoTerminal::SALA) {
                //                    $note->estado_terminal = EstadoTerminal::SIN_ESTADO; // ✅ enum, no string
                //                    $note->sent_to_sala_at = null;
                //                }
//
                //                // En todos los casos la mandamos a RETEN
                //                $note->reten = true;
                //                $note->save();
//
                //                $totalEnviadas++;
                //            }
                //        });
//
                //        $displayComercial = $retenCommercial->display_name; // "empleado_id - nombre apellidos"
                //
                //        $bodyLines = [
                //            "Total de notas enviadas a RETEN: {$totalEnviadas}",
                //            "Notas sin comercial asignadas ahora a {$displayComercial}: {$asignadasAReten}",
                //        ];
//
                //        Notification::make()
                //            ->title('Notas enviadas a RETEN')
                //            ->body(implode("\n", $bodyLines))
                //            ->success()
                //            ->send();
                //    })
                //    ->deselectRecordsAfterCompletion(),
                Tables\Actions\BulkAction::make('assignCommercialBulkAndSendToReten')
                    ->label('Asig. Come. + Enviar a RETEN')
                    ->icon('heroicon-s-user-plus')
                    ->color('warning')
                    ->form([
                        Forms\Components\Select::make('comercial_id')
                            ->label('Seleccionar Comercial')
                            ->options(function () {
                                return User::role(['commercial', 'team_leader', 'sales_manager'])
                                    ->whereNull('baja') // ajusta si tu campo real es fecha_baja
                                    ->orderBy('name')
                                    ->select('id', 'name', 'last_name', 'empleado_id')
                                    ->get()
                                    ->mapWithKeys(fn($u) => [
                                        $u->id => "{$u->empleado_id} {$u->name} {$u->last_name}",
                                    ])
                                    ->toArray();
                            })
                            ->searchable()
                            ->native(false)
                            ->placeholder('Sin asignar') // permite null
                            ->rules([
                                'nullable',
                                'integer',
                                Rule::exists('users', 'id')->where(function ($q) {
                                    $q->whereNull('baja')
                                        ->whereExists(function ($sq) {
                                            $sq->selectRaw(1)
                                                ->from('model_has_roles as mhr')
                                                ->join('roles as r', 'r.id', '=', 'mhr.role_id')
                                                ->whereColumn('mhr.model_id', 'users.id')
                                                ->where('mhr.model_type', User::class)
                                                ->whereIn('r.name', ['commercial', 'team_leader', 'sales_manager']);
                                        });
                                }),
                            ]),

                        Forms\Components\DatePicker::make('assignment_date')
                            ->label('Fecha de asignación')
                            ->hint('Si se deja vacío, se usará la fecha actual (solo si asignas comercial)')
                            ->required(false)
                            ->native(false),
                    ])
                    ->action(function (iterable $records, array $data): void {
                        try {
                            $comercialId = $data['comercial_id'] ?? null;

                            // Validación runtime
                            if (!empty($comercialId)) {
                                $isValid = User::query()
                                    ->where('id', $comercialId)
                                    ->whereNull('baja')
                                    ->whereHas('roles', fn($r) => $r->whereIn('name', ['commercial', 'team_leader', 'sales_manager']))
                                    ->exists();

                                if (!$isValid) {
                                    throw new \RuntimeException('El comercial seleccionado no está activo o no tiene un rol válido.');
                                }
                            }

                            $assignmentDate = !empty($comercialId)
                                ? ($data['assignment_date'] ?? now())
                                : null;

                            // Capturar from_comercial_id ANTES del update
                            $fromComercials = collect($records)->pluck('comercial_id', 'id');
                            $allRecords     = collect($records);
                            $blockedItems   = collect();
                            $cleanIds       = [];
                            $toResetIds     = [];

                            // ── Verificar ventas recientes (solo al asignar) ───────────────
                            if (!empty($comercialId)) {
                                $cutoff = now()->startOfMonth()->subMonthsNoOverflow(4);

                                foreach ($allRecords as $note) {
                                    $customer  = $note->customer;
                                    $isBlocked = false;

                                    if ($customer) {
                                        $phones = collect([
                                            $customer->phone,
                                            $customer->secondary_phone,
                                            $customer->third_phone,
                                            $customer->phone1_commercial,
                                            $customer->phone2_commercial,
                                        ])->filter()->values();

                                        $nameWords = collect(
                                            preg_split('/\s+/u', mb_strtolower(trim(
                                                ($customer->first_names ?? '') . ' ' . ($customer->last_names ?? '')
                                            )))
                                        )->filter(fn($w) => mb_strlen($w) > 2)->values();

                                        if ($phones->isNotEmpty() && $nameWords->isNotEmpty()) {
                                            $matchingCustomer = Customer::query()
                                                ->where(function ($q) use ($nameWords) {
                                                    foreach ($nameWords as $word) {
                                                        $q->orWhere(DB::raw('LOWER(first_names)'), 'like', "%{$word}%")
                                                          ->orWhere(DB::raw('LOWER(last_names)'), 'like', "%{$word}%");
                                                    }
                                                })
                                                ->where(function ($q) use ($phones) {
                                                    foreach ($phones as $phone) {
                                                        $q->orWhere('phone', $phone)
                                                          ->orWhere('secondary_phone', $phone)
                                                          ->orWhere('third_phone', $phone)
                                                          ->orWhere('phone1_commercial', $phone)
                                                          ->orWhere('phone2_commercial', $phone);
                                                    }
                                                })
                                                ->whereHas('ventas', fn($q) => $q->where('fecha_venta', '>=', $cutoff))
                                                ->first();

                                            if ($matchingCustomer) {
                                                $recentVenta = Venta::where('customer_id', $matchingCustomer->id)
                                                    ->where('fecha_venta', '>=', $cutoff)
                                                    ->with('comercial:id,empleado_id')
                                                    ->latest('fecha_venta')
                                                    ->first();

                                                $blockedItems->push([
                                                    'note_id'       => $note->id,
                                                    'nro_nota'      => $note->nro_nota,
                                                    'customer_name' => mb_strtoupper(trim(
                                                        ($matchingCustomer->first_names ?? '') . ' ' . ($matchingCustomer->last_names ?? '')
                                                    )),
                                                    'fecha_venta'   => $recentVenta
                                                        ? Carbon::parse($recentVenta->fecha_venta)->format('d/m/Y H:i')
                                                        : '—',
                                                    'comercial_emp' => $recentVenta?->comercial?->empleado_id ?? '—',
                                                ]);
                                                $isBlocked = true;
                                            }
                                        }
                                    }

                                    if (!$isBlocked) {
                                        $cleanIds[] = $note->id;
                                    }
                                }
                            } else {
                                $cleanIds = $allRecords->pluck('id')->all();
                            }
                            // ── Fin verificación ─────────────────────────────────────────

                            // 1) Asignar + reten en notas limpias
                            if (!empty($cleanIds)) {
                                Note::whereIn('id', $cleanIds)->update([
                                    'comercial_id'    => (!empty($comercialId) ? $comercialId : null),
                                    'assignment_date' => $assignmentDate,
                                    'reten'           => true,
                                ]);

                                // 2) Resetear TN a S/E para las que estén en SALA
                                $toResetIds = Note::whereIn('id', $cleanIds)
                                    ->where('estado_terminal', EstadoTerminal::SALA->value)
                                    ->pluck('id')
                                    ->all();

                                if (!empty($toResetIds)) {
                                    Note::whereIn('id', $toResetIds)->update([
                                        'estado_terminal' => EstadoTerminal::SIN_ESTADO->value,
                                        'sent_to_sala_at' => null,
                                    ]);
                                }

                                // Log de reasignación (solo notas limpias)
                                $batch = NoteReassignmentBatch::create([
                                    'author_id'       => auth()->id(),
                                    'to_comercial_id' => !empty($comercialId) ? $comercialId : null,
                                    'to_reten'        => true,
                                    'reassigned_at'   => now(),
                                ]);
                                foreach ($cleanIds as $noteId) {
                                    NoteReassignmentLog::create([
                                        'batch_id'          => $batch->id,
                                        'note_id'           => $noteId,
                                        'from_comercial_id' => $fromComercials[$noteId] ?? null,
                                    ]);
                                }
                            }

                            // Sin bloqueadas → notificación normal
                            if ($blockedItems->isEmpty()) {
                                Notification::make()
                                    ->title('Acción masiva completada')
                                    ->body(
                                        (empty($comercialId) ? 'Comercial removido' : 'Comercial asignado')
                                        . ' • Enviadas a RETEN: ' . count($cleanIds)
                                        . (!empty($toResetIds) ? ' • TN reiniciado en ' . count($toResetIds) . ' nota(s)' : '')
                                    )
                                    ->success()
                                    ->send();
                                return;
                            }

                            // Guardar IDs bloqueados en sesión
                            $blockedNoteIds = $blockedItems->pluck('note_id')->all();
                            session()->put('hor_blocked_note_ids', $blockedNoteIds);

                            // Construir tabla HTML
                            $rows = $blockedItems->map(fn($b) =>
                                "<tr style='border-bottom:1px solid #374151;'>"
                                . "<td style='padding:4px 8px;color:#fca5a5;font-weight:700;'>{$b['nro_nota']}</td>"
                                . "<td style='padding:4px 8px;color:#fff;font-weight:600;'>{$b['customer_name']}</td>"
                                . "<td style='padding:4px 8px;color:#fcd34d;'>{$b['fecha_venta']}</td>"
                                . "<td style='padding:4px 8px;color:#6ee7b7;'>ID: {$b['comercial_emp']}</td>"
                                . "</tr>"
                            )->join('');

                            $cleanMsg = !empty($cleanIds)
                                ? '<p style="margin-top:10px;color:#6ee7b7;font-weight:600;">✅ ' . count($cleanIds) . ' nota(s) asignadas y enviadas a RETEN.</p>'
                                : '';

                            $bodyHtml =
                                '<div style="font-size:13px;">'
                                . '<p style="margin-bottom:8px;color:#fca5a5;font-weight:600;">Clientes con venta en los últimos 4 meses:</p>'
                                . '<table style="width:100%;border-collapse:collapse;">'
                                . '<thead><tr style="background:#1f2937;">'
                                . '<th style="padding:4px 8px;text-align:left;color:#9ca3af;font-size:11px;text-transform:uppercase;">Nota</th>'
                                . '<th style="padding:4px 8px;text-align:left;color:#9ca3af;font-size:11px;text-transform:uppercase;">Cliente</th>'
                                . '<th style="padding:4px 8px;text-align:left;color:#9ca3af;font-size:11px;text-transform:uppercase;">Fecha Venta</th>'
                                . '<th style="padding:4px 8px;text-align:left;color:#9ca3af;font-size:11px;text-transform:uppercase;">Declarada por</th>'
                                . '</tr></thead>'
                                . "<tbody>{$rows}</tbody>"
                                . '</table>'
                                . $cleanMsg
                                . '</div>';

                            Notification::make()
                                ->title('⚠️ ' . $blockedItems->count() . ' nota(s) con ventas recientes detectadas')
                                ->body(new HtmlString($bodyHtml))
                                ->danger()
                                ->persistent()
                                ->actions([
                                    NotificationAction::make('delete_blocked_reten')
                                        ->label('ELIMINAR NOTAS CON VENTAS RECIENTES')
                                        ->color('danger')
                                        ->action(function () {
                                            $ids = session()->pull('hor_blocked_note_ids', []);
                                            if (!empty($ids)) {
                                                Note::whereIn('id', $ids)->delete();
                                            }
                                            Notification::make()
                                                ->title('✅ Notas eliminadas: ' . count($ids))
                                                ->success()
                                                ->send();
                                        }),
                                    NotificationAction::make('cerrar_reten')
                                        ->label('Salir')
                                        ->close(),
                                ])
                                ->send();

                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Error en acción masiva')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->deselectRecordsAfterCompletion(),

            ])
            ->deselectAllRecordsWhenFiltered(false);
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
            'index' => Pages\ListNotes::route('/'),
            'create' => Pages\CreateNote::route('/create'),
            'edit' => Pages\EditNote::route('/{record}/edit'),
        ];
    }
}
