<?php

namespace App\Filament\Commercial\Resources;

use App\Filament\Commercial\Resources\NoteResource\Pages;
use App\Filament\Commercial\Resources\NoteResource\RelationManagers;
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
use App\Models\PostalCode;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section;

class NoteResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Notas';

    protected static ?string $modelLabel = 'Nota';

    protected static ?string $pluralModelLabel = 'Notas';

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Información Personal')
                    ->schema([
                        TextEntry::make('customer.first_names')
                            ->label('Nombres'),

                        TextEntry::make('customer.last_names')
                            ->label('Apellidos'),

                        TextEntry::make('customer.phone')
                            ->label('Teléfono')
                            ->formatStateUsing(function ($state) {
                                // Eliminar espacios existentes y formatear de 3 en 3
                                $cleanNumber = str_replace(' ', '', $state);
                                return chunk_split($cleanNumber, 3, ' ');
                            }),

                        TextEntry::make('customer.secondary_phone')
                            ->label('Teléfono secundario')
                            ->formatStateUsing(function ($state) {
                                if (empty($state))
                                    return null;

                                // Eliminar espacios existentes y formatear de 3 en 3
                                $cleanNumber = str_replace(' ', '', $state);
                                return chunk_split($cleanNumber, 3, ' ');
                            }),

                        TextEntry::make('customer.email')
                            ->label('Correo electrónico'),

                        TextEntry::make('customer.age')
                            ->label('Edad'),
                    ])->columns(2),

                Section::make('Información de Contacto')
                    ->schema([
                        TextEntry::make('customer.postalCode.code')
                            ->label('Código postal')
                            ->formatStateUsing(function ($state, $record) {
                                $postalCode = $record->customer->postalCode->code ?? null;
                                $city = $record->customer->postalCode->city->title ?? null;

                                if ($postalCode && $city) {
                                    return "$city - $postalCode";
                                }

                                return $postalCode ?? $city ?? 'Sin ubicación';
                            }),

                        TextEntry::make('customer.primary_address')
                            ->label('Dirección principal'),

                        TextEntry::make('customer.secondary_address')
                            ->label('Dirección secundaria'),

                        TextEntry::make('customer.parish')
                            ->label('Parroquia'),
                    ])->columns(2),

                Section::make('Gestión Comercial')
                    ->schema([
                        TextEntry::make('fuente')
                            ->label('Fuente de la nota')
                            ->badge()
                            ->color(fn(FuenteNotas $state): string => $state->getColor()),

                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn(NoteStatus $state): string => $state->getColor()),
                    ]),

                Section::make('Visita')
                    ->schema([
                        TextEntry::make('visit_date')
                            ->label('Fecha de visita')
                            ->date(),

                        TextEntry::make('visit_schedule')
                            ->label('Horario de visita')
                            ->formatStateUsing(fn($state) => HorarioNotas::tryFrom($state)?->getLabel()),
                    ])->columns(2)
                    ->hidden(fn($record) => $record->status !== NoteStatus::CONTACTED->value),

                Section::make('Observaciones')
                    ->schema([
                        Components\RepeatableEntry::make('observations')
                            ->label('')
                            ->schema([
                                TextEntry::make('observation')
                                    ->label('')
                                    ->columnSpanFull()
                                    ->html()
                                    ->formatStateUsing(function ($state, $record) {
                                        $author = $record->author;
                                        $role = 'Tel. Op';
                                        if ($author->hasRole('commercial')) {
                                            $role = 'Com.';
                                        } elseif ($author->hasRole('head_of_room')) {
                                            $role = 'Tel. Op';
                                        }

                                        $date = $record->created_at->format('d/m/y');
                                        $observation = nl2br(e($state));

                                        return "<strong>{$author->name} {$author->last_name} ({$role}) - {$date}:</strong><br>{$observation}";
                                    }),
                            ])
                            ->grid(1),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([20, 25, 30, 40, 'all'])
            ->columns([
                Tables\Columns\TextColumn::make('fuente')
                    ->badge()
                    ->color(fn(FuenteNotas $state): string => $state->getColor())
                    ->formatStateUsing(fn(FuenteNotas $state): string => $state->getPuntaje() . ' pts')
                    ->label('Puntos'),

                Tables\Columns\TextColumn::make('user.empleado_id')
                    ->searchable()
                    ->label('T. Op.'),

                Tables\Columns\TextColumn::make('customer.name')
                    ->searchable()
                    ->label('Nombres y Apellidos'),

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
                    ->label('Comercial')
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

                Tables\Columns\TextColumn::make('fecha_asig')
                    ->label('Asignacion')
                    ->sortable(),
            ])
            ->filters([

            ])
            ->actions([

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
            'index' => Pages\ListNotes::route('/'),
            'view' => Pages\ViewNote::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('comercial_id', auth()->id())
            ->with(['observations.author']);
        ;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }
}
