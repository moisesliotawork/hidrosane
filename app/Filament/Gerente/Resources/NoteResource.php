<?php

namespace App\Filament\Gerente\Resources;

use App\Filament\Gerente\Resources\NoteResource\Pages;
use App\Filament\Gerente\Resources\NoteResource\RelationManagers;
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
use Filament\Notifications\Notification;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Filament\Support\Colors\Color;
use App\Enums\EstadoTerminal;
use App\Models\User;
use Illuminate\Validation\Rule;

class NoteResource extends Resource
{
    protected static ?string $model = Note::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Notas';

    protected static ?string $modelLabel = 'Nota';

    protected static ?string $pluralModelLabel = 'Notas';

    protected static ?int $navigationSort = 4;

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
                            ->maxLength(11)
                            ->minLength(11)
                            ->mask('999 999 999')
                            ->label('Teléfono secundario (opcional)')
                            ->validationMessages([
                                'min' => 'Debe tener exactamente 9 cifras',
                            ]),

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
                        Forms\Components\TextInput::make('postal_code')
                            ->required()
                            ->maxLength(255)
                            ->label('Codigo Postal'),

                        Forms\Components\TextInput::make('ciudad')
                            ->required()
                            ->maxLength(255)
                            ->label('Ciudad'),

                        Forms\Components\TextInput::make('provincia')
                            ->required()
                            ->maxLength(255)
                            ->label('Provincia'),

                        Forms\Components\TextInput::make('primary_address')
                            ->required()
                            ->maxLength(255)
                            ->label('Dirección principal'),

                        Forms\Components\TextInput::make('secondary_address')
                            ->maxLength(255)
                            ->label('Dirección secundaria (opcional)'),

                        Forms\Components\TextInput::make('parish')
                            ->maxLength(255)
                            ->label('Parroquia (opcional)'),

                        Forms\Components\TextInput::make('ayuntamiento')
                            ->maxLength(255)
                            ->label('Ayuntamiento'),
                    ])->columns(2),

                Forms\Components\Section::make('Gestión Comercial')
                    ->schema([

                        Forms\Components\Select::make('fuente')
                            ->options(FuenteNotas::options())
                            ->required()
                            ->native(false)
                            ->label('Fuente de la nota')
                            ->hidden(fn(string $operation): bool => $operation === 'create'),

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
                    ->badge()
                    ->sortable()
                    ->color(Color::Gray)
                    ->label('# Nota'),

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
                    ->sortable(),

                Tables\Columns\TextColumn::make('visit_schedule')
                    ->badge()
                    ->color(Color::Gray)
                    ->label('Horario')
                    ->sortable(),
                Tables\Columns\TextColumn::make('estado_terminal')
                    ->badge()
                    ->formatStateUsing(
                        fn(Note $record): string =>
                        $record->estado_terminal?->label() ?? 'S/E'
                    )
                    ->color(function (Note $record): string {
                        $et = $record->estado_terminal;

                        return match ($et) {
                            EstadoTerminal::NUL => 'danger',
                            EstadoTerminal::VENTA => 'success',
                            EstadoTerminal::CONFIRMADO => 'orange',
                            EstadoTerminal::SALA => 'pink',
                            EstadoTerminal::AUSENTE => 'warning', // ← faltaba
                            EstadoTerminal::SIN_ESTADO => 'gray',
                            default => 'gray',    // ← por seguridad futura
                        };
                    })
                    ->label('TN')
                    ->sortable(),


                Tables\Columns\TextColumn::make('reten')
                    ->label('Reten')
                    ->badge()
                    ->formatStateUsing(fn(bool $state) => $state ? 'SI' : 'NO')
                    ->color(fn(bool $state) => $state ? 'success' : 'danger')
                    ->sortable(),
            ])
            ->defaultSort('assignment_date', 'desc')
            ->filters([

                Tables\Filters\Filter::make('assignment_date')
                    ->form([
                        Forms\Components\DatePicker::make('assignment_date')
                            ->label('Fecha exacta de asignación')
                            ->displayFormat('d/m/Y')
                            ->native(false)
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

                        return 'Fecha de asignación: ' . Carbon::parse($data['assignment_date'])->format('d/m/Y');
                    }),

                Tables\Filters\SelectFilter::make('comercial_id')
                    ->label('Comercial')
                    ->options(function () {
                        return \App\Models\User::role(['commercial', 'team_leader']) // 👈 ambos roles
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
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label(''),
                Tables\Actions\DeleteAction::make()
                    ->label(''),

                Tables\Actions\Action::make('assignCommercial')
                    ->label('')
                    ->icon('heroicon-s-user-plus')
                    ->form([
                        Forms\Components\Select::make('comercial_id')
                            ->label('Seleccionar Comercial')
                            ->options(function () {
                                return User::role(['commercial', 'team_leader'])
                                    ->whereNull('baja')          // <-- SOLO activos
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
                            ->placeholder('Sin asignar')
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
                                                ->whereIn('r.name', ['commercial', 'team_leader']);
                                        });
                                }),
                            ]),
                        Forms\Components\DatePicker::make('assignment_date')
                            ->label('Fecha de asignación')
                            ->hint('Si se deja vacío, se usará la fecha actual')
                            ->required(false),
                    ])
                    ->action(function (Note $record, array $data): void {
                        try {
                            $comercialId = $data['comercial_id'] ?? null;

                            // doble verificación en runtime (aquí sí podemos usar Eloquent + whereHas)
                            if (!empty($comercialId)) {
                                $isValid = User::query()
                                    ->where('id', $comercialId)
                                    ->whereNull('baja')
                                    ->whereHas('roles', fn($r) => $r->whereIn('name', ['commercial', 'team_leader']))
                                    ->exists();

                                if (!$isValid) {
                                    throw new \RuntimeException('El comercial seleccionado no está activo o no tiene un rol válido.');
                                }
                            }

                            $assignmentDate = !empty($comercialId) ? ($data['assignment_date'] ?? now()) : null;

                            $updates = [
                                'comercial_id' => $comercialId ?: null,
                                'assignment_date' => $assignmentDate,
                            ];

                            if ($record->estado_terminal === EstadoTerminal::SALA) {
                                $updates['estado_terminal'] = EstadoTerminal::SIN_ESTADO->value;
                                $updates['sent_to_sala_at'] = null;
                            }

                            $record->update($updates);

                            Notification::make()
                                ->title(empty($comercialId)
                                    ? 'Comercial removido correctamente'
                                    : 'Comercial asignado correctamente: ' . User::find($comercialId)?->name)
                                ->success()
                                ->send();

                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Error al actualizar comercial')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->label('Eliminar seleccionadas')
                    ->modalHeading('Eliminar notas seleccionadas')
                    ->modalDescription('¿Estás seguro de que quieres eliminar las notas seleccionadas? Esta acción no se puede deshacer.')
                    ->modalSubmitActionLabel('Sí, eliminar')
                    ->successNotificationTitle('Notas eliminadas correctamente'),
                Tables\Actions\BulkAction::make('assignCommercialBulk')
                    ->label('Asignar comercial')
                    ->icon('heroicon-s-user-plus')
                    ->form([
                        Forms\Components\Select::make('comercial_id')
                            ->label('Seleccionar Comercial')
                            ->options(function () {
                                return User::role(['commercial', 'team_leader'])
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
                                                ->whereIn('r.name', ['commercial', 'team_leader']);
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

                            // Doble verificación en runtime (aquí sí Eloquent + whereHas)
                            if (!empty($comercialId)) {
                                $isValid = User::query()
                                    ->where('id', $comercialId)
                                    ->whereNull('baja') // activo
                                    ->whereHas('roles', fn($r) => $r->whereIn('name', ['commercial', 'team_leader']))
                                    ->exists();

                                if (!$isValid) {
                                    throw new \RuntimeException('El comercial seleccionado no está activo o no tiene un rol válido.');
                                }
                            }

                            $assignmentDate = !empty($comercialId)
                                ? ($data['assignment_date'] ?? now())
                                : null;

                            $recordIds = collect($records)->pluck('id')->all();

                            // 1) Reasignar comercial/fecha
                            Note::whereIn('id', $recordIds)->update([
                                'comercial_id' => (!empty($comercialId) ? $comercialId : null),
                                'assignment_date' => $assignmentDate,
                            ]);

                            // 2) Resetear TN a S/E para las que estén en SALA
                            $toResetIds = Note::whereIn('id', $recordIds)
                                ->where('estado_terminal', EstadoTerminal::SALA->value)
                                ->pluck('id')
                                ->all();

                            if (!empty($toResetIds)) {
                                Note::whereIn('id', $toResetIds)->update([
                                    'estado_terminal' => EstadoTerminal::SIN_ESTADO->value,
                                    'sent_to_sala_at' => null,
                                ]);
                            }

                            Notification::make()
                                ->title('Asignación masiva completada')
                                ->body(
                                    (empty($comercialId) ? 'Comercial removido' : 'Comercial asignado')
                                    . (!empty($toResetIds) ? ' • TN reiniciado en ' . count($toResetIds) . ' nota(s)' : '')
                                )
                                ->success()
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
        $desde = now()->subDays(5)->toDateString();
        $hasta = now()->toDateString();

        return parent::getEloquentQuery()
            ->whereBetween(\DB::raw('DATE(assignment_date)'), [$desde, $hasta])
            ->orderByDesc('assignment_date')
            ->orderByDesc('id');
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
