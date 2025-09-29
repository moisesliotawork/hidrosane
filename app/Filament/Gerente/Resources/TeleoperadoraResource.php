<?php

namespace App\Filament\Gerente\Resources;

use App\Filament\Gerente\Resources\TeleoperadoraResource\Pages;
use App\Filament\HeadOfRoom\Resources\TeleoperadoraResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\TextColumn;
use App\Enums\EstadoTerminal;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Select;


class TeleoperadoraResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Teleoperadoras';
    protected static ?string $pluralModelLabel = 'Teleoperadoras';
    protected static ?string $modelLabel = 'Teleoperadora';

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                // Lee el filtro, soportando claves viejas y nuevas
                $filters = request()->input('tableFilters', []);
                $choice =
                    data_get($filters, 'periodo.period') // filtro nuevo
                    ?? data_get($filters, 'period.value') // filtro viejo
                    ?? 'prev';

                if ($choice === 'all') {
                    // Desde siempre (sin fechas)
                    return $query->withCount([
                        'notes as confirmadas_count' => fn($q) =>
                            $q->where('estado_terminal', \App\Enums\EstadoTerminal::CONFIRMADO->value),
                        'notes as vendidas_count' => fn($q) =>
                            $q->where('estado_terminal', \App\Enums\EstadoTerminal::VENTA->value),
                        //total de notas asociadas
                        'notes as aproduccion_count' => fn($q) => $q,
                    ]);
                }

                // Mes actual / anterior / hace dos meses
                $offset = match ($choice) {
                    'this' => 0,
                    'prev' => 1,
                    'two' => 2,
                    default => 0,
                };

                // Normaliza a UTC y usa límites de día completos
                $tz = config('app.timezone', 'UTC');
                $start = \Carbon\Carbon::now($tz)->subMonths($offset)->startOfMonth()->startOfDay()->utc()->toDateTimeString();
                $end = \Carbon\Carbon::now($tz)->subMonths($offset)->endOfMonth()->endOfDay()->utc()->toDateTimeString();

                return $query->withCount([
                    'notes as confirmadas_count' => fn($q) =>
                        $q->where('estado_terminal', \App\Enums\EstadoTerminal::CONFIRMADO->value)
                            ->whereBetween('created_at', [$start, $end]),
                    'notes as vendidas_count' => fn($q) =>
                        $q->where('estado_terminal', \App\Enums\EstadoTerminal::VENTA->value)
                            ->whereBetween('created_at', [$start, $end]),
                    // NUEVO: total de notas asociadas dentro del período
                    'notes as aproduccion_count' => fn($q) =>
                        $q->whereBetween('created_at', [$start, $end]),
                ]);
            })
            ->columns([
                TextColumn::make('empleado_id')
                    ->label('Empleado')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('full_name')
                    ->label('Nombre')
                    ->state(fn($record) => "{$record->name} {$record->last_name}")
                    ->sortable(query: function (Builder $query, string $direction) {
                        $query->orderBy('users.name', $direction)
                            ->orderBy('users.last_name', $direction);
                    })
                    ->searchable(query: function (Builder $query, string $search) {
                        $query->where(function ($q) use ($search) {
                            $q->where('users.name', 'like', "%{$search}%")
                                ->orWhere('users.last_name', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('confirmadas_count')
                    ->label('CONFIRMADAS')
                    ->state(fn($record) => (int) ($record->confirmadas_count ?? 0))
                    ->badge()
                    ->color(fn($record) => ((int) ($record->confirmadas_count ?? 0)) === 0 ? 'gray' : 'info')
                    ->sortable(query: fn(Builder $q, string $dir) => $q->orderBy('confirmadas_count', $dir)),

                TextColumn::make('vendidas_count')
                    ->label('VENTAS')
                    ->state(fn($record) => (int) ($record->vendidas_count ?? 0))
                    ->badge()
                    ->color(fn($record) => ((int) ($record->vendidas_count ?? 0)) === 0 ? 'gray' : 'success')
                    ->sortable(query: fn(Builder $q, string $dir) => $q->orderBy('vendidas_count', $dir)),

                // NUEVA COLUMNA
                TextColumn::make('aproduccion_count')
                    ->label('PRODUCCIÓN')
                    ->state(fn($record) => (int) ($record->aproduccion_count ?? 0))
                    ->badge()
                    ->color(fn($record) => ((int) ($record->aproduccion_count ?? 0)) === 0 ? 'gray' : 'warning')
                    ->sortable(query: fn(Builder $q, string $dir) => $q->orderBy('aproduccion_count', $dir)),

                TextColumn::make('total_cv')
                    ->label('TOTAL')
                    ->state(
                        fn($record) =>
                        (int) ($record->confirmadas_count ?? 0)
                        + (int) ($record->vendidas_count ?? 0)
                    )
                    ->badge()
                    ->color(fn($state) => ((int) $state) === 0 ? 'gray' : 'primary')
                    ->sortable(
                        query: fn(Builder $q, string $dir) =>
                        $q->orderByRaw('(COALESCE(confirmadas_count,0) + COALESCE(vendidas_count,0)) ' . $dir)
                    ),
            ])
            ->filters([
                Filter::make('periodo')
                    ->label('Periodo')
                    ->form([
                        Select::make('period')
                            ->label('Periodo')
                            ->options([
                                'this' => 'Mes actual',
                                'prev' => 'Mes anterior',
                                'two' => 'Hace dos meses',
                                'all' => 'Desde siempre',
                            ])
                            ->default('prev')
                            ->native(false),
                    ])
                    ->indicateUsing(function (array $data): ?string {
                        return match ($data['period'] ?? 'prev') {
                            'this' => 'Periodo: Mes actual',
                            'prev' => 'Periodo: Mes anterior',
                            'two' => 'Periodo: Hace dos meses',
                            'all' => 'Periodo: Desde siempre',
                            default => null,
                        };
                    }),
            ])
            ->actions([])
            ->bulkActions([]);
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
            'index' => Pages\ListTeleoperadoras::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return \App\Models\User::query()
            ->select('users.*')
            ->role(['teleoperator', 'head_of_room'])
            ->distinct('users.id');
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
