<?php

namespace App\Filament\HeadOfRoom\Resources;

use App\Enums\NoteStatus;
use App\Filament\HeadOfRoom\Resources\RetenResource\Pages;
use App\Models\Note;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RetenResource extends Resource
{
    protected static ?string $model = Note::class;

    protected static ?string $navigationLabel = 'RETEN';

    protected static ?string $pluralModelLabel = 'RETEN';

    protected static ?string $modelLabel = 'Nota RETEN';

    protected static ?string $slug = 'reten';

    protected static ?string $navigationIcon = 'heroicon-o-pause-circle';

    protected static ?int $navigationSort = 6;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nro_nota')
                    ->label('Nro. Nota')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('cliente')
                    ->label('Cliente')
                    ->getStateUsing(fn (Note $record): string =>
                        strtoupper(trim(($record->customer?->first_names ?? '') . ' ' . ($record->customer?->last_names ?? '')))
                    )
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('customer', fn ($q) =>
                            $q->where('first_names', 'like', "%{$search}%")
                                ->orWhere('last_names', 'like', "%{$search}%")
                        );
                    }),

                TextColumn::make('tlf1')
                    ->label('Tlf 1')
                    ->getStateUsing(fn (Note $record): string =>
                        $record->customer?->phone1_commercial ?? '—'
                    ),

                TextColumn::make('tlf2')
                    ->label('Tlf 2')
                    ->getStateUsing(fn (Note $record): string =>
                        $record->customer?->phone2_commercial ?? '—'
                    ),

                TextColumn::make('idempleado')
                    ->label('Idempleado')
                    ->getStateUsing(fn (Note $record): string =>
                        $record->comercial?->empleado_id ?? '—'
                    )
                    ->sortable(query: fn (Builder $query, string $direction) =>
                        $query->leftJoin('users as com_u', 'notes.comercial_id', '=', 'com_u.id')
                            ->orderBy('com_u.empleado_id', $direction)
                    ),

                TextColumn::make('nombre_comercial')
                    ->label('Nombre Comercial')
                    ->getStateUsing(fn (Note $record): string =>
                        trim(($record->comercial?->name ?? '') . ' ' . ($record->comercial?->last_name ?? '')) ?: '—'
                    ),

                TextColumn::make('assignment_date')
                    ->label('Fecha Asignación')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Fecha Creación Nota')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof NoteStatus ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof NoteStatus ? $state->getColor() : null),

                TextColumn::make('visit_date')
                    ->label('Fecha Visita')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('fecha_exacta')
                    ->label('Fecha exacta')
                    ->form([
                        DatePicker::make('fecha')
                            ->label('Fecha')
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder =>
                        $query->when($data['fecha'], fn ($q) =>
                            $q->whereDate('created_at', $data['fecha'])
                        )
                    )
                    ->indicateUsing(fn (array $data): ?string =>
                        $data['fecha']
                            ? 'Fecha: ' . Carbon::parse($data['fecha'])->format('d/m/Y')
                            : null
                    ),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->actions([])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('reten', true)
            ->with([
                'customer:id,first_names,last_names,phone1_commercial,phone2_commercial',
                'comercial:id,name,last_name,empleado_id',
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRetens::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
