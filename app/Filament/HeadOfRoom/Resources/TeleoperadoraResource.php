<?php

namespace App\Filament\HeadOfRoom\Resources;

use App\Filament\HeadOfRoom\Resources\TeleoperadoraResource\Pages;
use App\Filament\HeadOfRoom\Resources\TeleoperadoraResource\RelationManagers;
use App\Models\User;
use App\Models\Note;
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

    public static function form(Form $form): Form
    {
        return $form->schema([
            //
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                // Lee el filtro (compatible con claves viejas y nuevas)
                $filters = request()->input('tableFilters', []);
                $choice =
                    data_get($filters, 'periodo.period') // filtro nuevo
                    ?? data_get($filters, 'period.value') // filtro viejo
                    ?? 'prev';

                // Calcula mes objetivo (this/prev/two). Para "all" no habrá filtro de fecha.
                $offset = match ($choice) {
                    'this' => 0,
                    'prev' => 1,
                    'two' => 2,
                    default => 0,
                };

                // Fechas (solo día, sin horas) para usar con DATE(created_at)
                $startDate = now()->subMonths($offset)->startOfMonth()->toDateString();
                $endDate = now()->subMonths($offset)->endOfMonth()->toDateString();

                // Subselects que NO dependen de la relación definida en el modelo:
                // Se comparan columnas directamente: notes.user_id = users.id
                // Para que coincida con tu SQL:
                //   SELECT COUNT(*) FROM notes
                //   WHERE user_id = :id AND estado_terminal = 'venta'
                //     AND DATE(created_at) BETWEEN :start AND :end
                $query->addSelect([
                    'confirmadas_count' => Note::query()
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('notes.user_id', 'users.id')
                        ->when(
                            $choice !== 'all',
                            fn($q) =>
                            $q->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])
                        )
                        ->where('estado_terminal', EstadoTerminal::CONFIRMADO->value),

                    'vendidas_count' => Note::query()
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('notes.user_id', 'users.id')
                        ->when(
                            $choice !== 'all',
                            fn($q) =>
                            $q->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])
                        )
                        ->where('estado_terminal', EstadoTerminal::VENTA->value),
                ]);

                return $query;
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

                TextColumn::make('total_cv')
                    ->label('TOTAL')
                    ->state(
                        fn($record) =>
                        (int) ($record->confirmadas_count ?? 0) + (int) ($record->vendidas_count ?? 0)
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
        return User::query()
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
