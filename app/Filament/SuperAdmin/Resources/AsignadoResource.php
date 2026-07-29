<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\AsignadoResource\Pages;
use App\Models\Note;
use App\Models\User;
use Carbon\Carbon;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AsignadoResource extends Resource
{
    protected static ?string $model = Note::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'ASIGNADO';

    protected static ?string $modelLabel = 'Asignado';

    protected static ?string $pluralModelLabel = 'Asignados';

    protected static ?string $slug = 'asignado';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->assignedToCommercial()
            ->whereHas('comercial', function (Builder $q) {
                $q->whereNull('baja')
                    ->whereHas('roles', fn (Builder $r) => $r->whereIn('name', [
                        'commercial',
                        'team_leader',
                        'sales_manager',
                    ]));
            })
            ->with([
                'comercial.roles:id,name',
                'assignedBy:id,name,last_name,empleado_id',
                'customer:id,first_names,last_names,phone,postal_code',
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('assignment_date')
                    ->label('Fecha asignación')
                    ->badge()
                    ->color('danger')
                    ->weight('bold')
                    ->toggleable()
                    ->formatStateUsing(
                        fn (Note $record): string => $record->effectiveAssignmentDate()
                            ? $record->effectiveAssignmentDate()->timezone(Note::businessTimezone())->format('d/m/Y')
                            : '—'
                    )
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw(
                            'COALESCE(assignment_date, visit_date) '.($direction === 'desc' ? 'desc' : 'asc')
                        );
                    }),

                Tables\Columns\TextColumn::make('comercial.empleado_id')
                    ->label('ID empleado')
                    ->badge()
                    ->color('info')
                    ->toggleable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('comercial.name')
                    ->label('Comercial')
                    ->weight('bold')
                    ->toggleable()
                    ->formatStateUsing(function ($state, Note $record): string {
                        $u = $record->comercial;
                        if (! $u || ! $u->id) {
                            return 'Sin comercial';
                        }

                        $roleNames = $u->roles->pluck('name');
                        $tag = match (true) {
                            $roleNames->contains('team_leader') && $roleNames->contains('commercial') => 'TL/COM',
                            $roleNames->contains('team_leader') => 'TL',
                            $roleNames->contains('sales_manager') => 'JV',
                            default => 'COM',
                        };

                        return trim("{$u->name} {$u->last_name}")." ({$tag})";
                    }),

                Tables\Columns\TextColumn::make('assignedBy.name')
                    ->label('Asignado por')
                    ->badge()
                    ->color('warning')
                    ->toggleable()
                    ->formatStateUsing(function ($state, Note $record): string {
                        $by = $record->assignedBy;
                        if (! $by) {
                            return '—';
                        }

                        return trim(($by->empleado_id ? $by->empleado_id.' ' : '').$by->name.' '.($by->last_name ?? ''));
                    }),

                Tables\Columns\TextColumn::make('nro_nota')
                    ->label('Nº Nota')
                    ->badge()
                    ->color('yellow')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->weight('bold')
                    ->toggleable()
                    ->formatStateUsing(fn ($state) => strtoupper((string) ($state ?? '—')))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('customer', function (Builder $q) use ($search) {
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
                    ->formatStateUsing(fn (?string $state): string => filled($state)
                        ? trim(chunk_split(str_replace(' ', '', $state), 3, ' '))
                        : '—'
                    ),
            ])
            ->defaultGroup('comercial_id')
            ->groups([
                Tables\Grouping\Group::make('comercial_id')
                    ->label('Comercial')
                    ->getTitleFromRecordUsing(function (Note $record): string {
                        $u = $record->comercial;
                        if (! $u || ! $u->id) {
                            return 'Sin comercial';
                        }

                        $roleNames = $u->roles->pluck('name');
                        $tag = match (true) {
                            $roleNames->contains('team_leader') && $roleNames->contains('commercial') => 'TL/COM',
                            $roleNames->contains('team_leader') => 'TL',
                            $roleNames->contains('sales_manager') => 'JV',
                            default => 'COM',
                        };

                        return trim("{$u->empleado_id} {$u->name} {$u->last_name}")." ({$tag})";
                    })
                    ->collapsible(),
            ])
            ->filters([
                Tables\Filters\Filter::make('buscar_fecha')
                    ->label('Buscar Fecha')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('date')
                            ->label('Buscar Fecha')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->timezone('Europe/Madrid')
                            ->closeOnDateSelection(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['date'] ?? null,
                            fn (Builder $q, $date) => $q->whereEffectiveAssignmentDate($date),
                        );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (blank($data['date'] ?? null)) {
                            return null;
                        }

                        return 'Fecha: '.Carbon::parse($data['date'])->format('d/m/Y');
                    }),

                Tables\Filters\SelectFilter::make('comercial_id')
                    ->label('Comercial / TL')
                    ->options(function (): array {
                        return User::query()
                            ->whereNull('baja')
                            ->whereHas('roles', fn ($r) => $r->whereIn('name', [
                                'commercial',
                                'team_leader',
                                'sales_manager',
                            ]))
                            ->orderBy('empleado_id')
                            ->get(['id', 'name', 'last_name', 'empleado_id'])
                            ->mapWithKeys(fn (User $u) => [
                                $u->id => trim("{$u->empleado_id} {$u->name} {$u->last_name}"),
                            ])
                            ->all();
                    })
                    ->searchable()
                    ->preload(),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(2)
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAsignados::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
