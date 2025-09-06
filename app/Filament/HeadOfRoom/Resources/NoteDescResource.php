<?php

namespace App\Filament\HeadOfRoom\Resources;

use App\Filament\HeadOfRoom\Resources\NoteDescResource\Pages;
use App\Filament\HeadOfRoom\Resources\NoteDescResource\RelationManagers;
use App\Models\Note;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Notifications\Notification;
use Carbon\Carbon;
use Filament\Support\Colors\Color;
use Filament\Tables\Actions\ViewAction;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\{
    Section,
    TextEntry,
    RepeatableEntry
};
use App\Enums\{
    EstadoTerminal,
    NoteStatus,
    FuenteNotas,
    HorarioNotas
};
use App\Models\User;



class NoteDescResource extends Resource
{
    protected static ?string $model = Note::class;

    protected static ?string $navigationLabel = 'Notas Nulos - Ausentes';
    protected static ?string $pluralModelLabel = 'Nulos - Ausentes';
    protected static ?string $modelLabel = 'Nulos - Ausentes';
    protected static ?string $slug = 'notas-desc';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with(['observations.author'])
            ->whereIn('estado_terminal', [
                EstadoTerminal::NUL->value,
                EstadoTerminal::AUSENTE->value,
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            /* 1. Información personal */
            Section::make('Información Personal')
                ->schema([
                    TextEntry::make('first_names')->label('Nombres'),
                    TextEntry::make('last_names')->label('Apellidos'),
                    TextEntry::make('phone')
                        ->label('Teléfono')
                        ->formatStateUsing(
                            fn(string $state) =>
                            chunk_split(str_replace(' ', '', $state), 3, ' ')
                        ),
                    TextEntry::make('secondary_phone')->label('Tel. secundario'),
                    TextEntry::make('email')->label('Correo'),
                    TextEntry::make('age')->label('Edad'),
                ])
                ->columns(2),

            /* 2. Información de contacto */
            Section::make('Información de Contacto')
                ->schema([
                    TextEntry::make('customer.postalCode.code')->label('CP'),
                    TextEntry::make('customer.postalCode.city.title')->label('Ciudad'),
                    TextEntry::make('primary_address')->label('Dirección'),
                    TextEntry::make('secondary_address')->label('Dirección 2'),
                    TextEntry::make('parish')->label('Parroquia'),
                    TextEntry::make('ayuntamiento')->label('Ayuntamiento'),
                ])
                ->columns(2),

            /* 3. Gestión comercial */
            Section::make('Gestión Comercial')
                ->schema([
                    TextEntry::make('fuente')
                        ->badge()
                        ->label('Fuente')
                        ->color(fn(FuenteNotas $state) => $state->getColor())
                        ->formatStateUsing(fn(FuenteNotas $state) => $state->getLabel()),

                    TextEntry::make('status')
                        ->badge()
                        ->label('Estado')
                        ->color(fn(NoteStatus $state) => $state->getColor())
                        ->formatStateUsing(fn(NoteStatus $state) => $state->label()),
                ]),

            /* 4. Visita */
            Section::make('Visita')
                ->schema([
                    TextEntry::make('visit_date')->label('Fecha')->date('d/m/Y'),
                    TextEntry::make('visit_schedule')
                        ->badge()
                        ->label('Horario')
                        ->color(Color::Gray)
                        ->formatStateUsing(
                            fn($state) =>
                            HorarioNotas::tryFrom($state)?->getLabel() ?? $state
                        ),
                ])
                ->columns(2)
                ->visible(fn($record) => $record->status === NoteStatus::CONTACTED),

            /* 5. Comercial */
            Section::make('Comercial')
                ->schema([
                    TextEntry::make('comercial.empleado_id')
                        ->badge()
                        ->label('Código')
                        ->color('success'),
                    TextEntry::make('comercial.name')->label('Nombre'),
                    TextEntry::make('comercial.last_name')->label('Apellidos'),
                    TextEntry::make('comercial.phone')->label('Teléfono'),
                ])
                ->columns(2),

            /* 8. Observaciones */
            Section::make('Observaciones')
                ->schema([
                    RepeatableEntry::make('observations')
                        ->label('')            // sin etiqueta de grupo
                        ->schema([
                            TextEntry::make('author_id')
                                ->label('Autor')
                                ->formatStateUsing(function ($state) {     //  ✅  $state en vez de $id
                                    static $cache = [];

                                    if (blank($state)) {
                                        return '—';
                                    }

                                    // Cache para evitar N+1 si hay muchas observaciones
                                    return $cache[$state] ??= \App\Models\User::find($state)?->name
                                        ?? "ID $state";
                                })
                                ->badge()
                                ->color('info'),

                            // Texto de la observación
                            TextEntry::make('observation')
                                ->label('Comentarios')
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->visible(fn($state) => filled($state)),
                ]),



            /* 7. Estado terminal */
            Section::make('Terminal')
                ->schema([
                    TextEntry::make('estado_terminal')
                        ->badge()
                        ->label('TN')
                        ->color(fn($record) => match ($record->estado_terminal) {
                            EstadoTerminal::NUL => 'danger',
                            EstadoTerminal::VENTA => 'success',
                            EstadoTerminal::CONFIRMADO => 'orange',
                            EstadoTerminal::SALA => 'pink',
                            default => 'gray',
                        })
                        ->formatStateUsing(fn($record) => $record->estado_terminal->label()),
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
                    ->label('# Nota')
                    ->formatStateUsing(function (string $state) {
                        // Asegurarse que tiene exactamente 5 caracteres
                        if (strlen($state) === 5) {
                            return substr($state, 0, 3) . ' ' . substr($state, 3, 2);
                        }
                        return $state; // Si no tiene 5 caracteres, devolver el valor original
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
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('customer', function ($q) use ($search) {
                            $q->where('first_names', 'like', "%{$search}%")
                                ->orWhere('last_names', 'like', "%{$search}%");
                        });
                    }),

                Tables\Columns\TextColumn::make('customer.phone')
                    ->searchable()
                    ->label('Teléfono')
                    ->html()
                    ->formatStateUsing(fn($state) => '<span style="font-size: 1rem; font-weight: bold;">' .
                        chunk_split(str_replace(' ', '', $state), 3, ' ') . '</span>'),

                Tables\Columns\TextColumn::make('customer.postalCode.code')
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
                    ->formatStateUsing(fn(Note $record): string => $record->estado_terminal->label())
                    ->color(fn(Note $record): string => match ($record->estado_terminal) {
                        EstadoTerminal::NUL => 'danger',
                        EstadoTerminal::AUSENTE => 'gray',
                    })
                    ->label('TN')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(NoteStatus::options())
                    ->label('Estado'),

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
                        $commercials = \App\Models\User::role('commercial')
                            ->select('id', 'name', 'last_name', 'empleado_id')
                            ->get();

                        return $commercials->mapWithKeys(function ($user) {
                            return [$user->id => "{$user->empleado_id} {$user->name} {$user->last_name}"];
                        })->toArray();
                    })
                    ->searchable()
                    ->native(false),
            ])
            ->actions([
                ViewAction::make(),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNoteDescs::route('/'),
            'view' => Pages\ViewNoteDesc::route('/{record}'),
        ];
    }

    public static function canEdited(): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
