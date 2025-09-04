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


class TeleoperadoraResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Teleoperadoras';
    protected static ?string $pluralModelLabel = 'Teleoperadoras';
    protected static ?string $modelLabel = 'Teleoperadora';

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
                SelectFilter::make('period')
                    ->label('Periodo')
                    ->options([
                        'this' => 'Mes actual',
                        'prev' => 'Mes anterior',
                        'two' => 'Hace dos meses',
                        'all' => 'Desde siempre',
                    ])
                    ->default('this'), // o 'all' si quieres el histórico por defecto
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
        $query = User::query()
            ->select('users.*')
            ->role(['teleoperator', 'head_of_room'])
            ->distinct('users.id');

        // Lee el filtro desde la querystring de Filament
        $choice = request('tableFilters.period.value', 'this'); // cámbialo a 'all' si prefieres

        if ($choice === 'all') {
            return $query->withCount([
                'notes as confirmadas_count' => fn($q) =>
                    $q->where('estado_terminal', EstadoTerminal::CONFIRMADO->value),
                'notes as vendidas_count' => fn($q) =>
                    $q->where('estado_terminal', EstadoTerminal::VENTA->value),
            ]);
        }

        $offset = match ($choice) {
            'this' => 0,
            'prev' => 1,
            'two' => 2,
            default => 0,
        };

        // Ajusta el timezone si tus fechas "humanas" son Europe/Madrid pero guardas en UTC
        $tz = config('app.timezone', 'UTC');

        $start = Carbon::now($tz)->subMonths($offset)->startOfMonth()->utc();
        $end = Carbon::now($tz)->subMonths($offset)->endOfMonth()->utc();

        return $query->withCount([
            'notes as confirmadas_count' => fn($q) =>
                $q->where('estado_terminal', EstadoTerminal::CONFIRMADO->value)
                    ->whereBetween('notes.created_at', [$start, $end]),
            'notes as vendidas_count' => fn($q) =>
                $q->where('estado_terminal', EstadoTerminal::VENTA->value)
                    ->whereBetween('notes.created_at', [$start, $end]),
        ]);
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
