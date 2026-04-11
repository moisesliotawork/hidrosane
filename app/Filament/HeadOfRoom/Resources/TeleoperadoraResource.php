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
        return $form->schema([
            //
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('empleado_id')
                    ->label('ID')
                    ->badge()
                    ->color("pink")
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
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->color(fn($record) => ((int) ($record->confirmadas_count ?? 0)) === 0 ? 'gray' : 'warning')
                    ->sortable(query: fn(Builder $q, string $dir) => $q->orderBy('confirmadas_count', $dir)),

                TextColumn::make('vendidas_count')
                    ->label('VENTAS')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->state(fn($record) => (int) ($record->vendidas_count ?? 0))
                    ->badge()
                    ->color(fn($record) => ((int) ($record->vendidas_count ?? 0)) === 0 ? 'gray' : 'success')
                    ->sortable(query: fn(Builder $q, string $dir) => $q->orderBy('vendidas_count', $dir)),

                TextColumn::make('aproduccion_count')
                    ->label('Produccion')
                    ->state(fn($record) => (int) ($record->aproduccion_count ?? 0))
                    ->badge()
                    ->color(fn($record) => ((int) ($record->aproduccion_count ?? 0)) === 0 ? 'gray' : 'success')
                    ->sortable(query: fn(Builder $q, string $dir) => $q->orderBy('aproduccion_count', $dir)),

                TextColumn::make('total_cv')
                    ->label('Vtas/Conf.')
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

                TextColumn::make('pct_conf')
                    ->label('% Conf.')
                    ->state(function ($record) {
                        $conf = (int) ($record->confirmadas_count ?? 0);
                        $vent = (int) ($record->vendidas_count ?? 0);
                        $prod = (int) ($record->aproduccion_count ?? 0);

                        if ($prod === 0) {
                            return 0;
                        }

                        $pct = (($conf + $vent) / $prod) * 100;
                        // Redondeo a 2 decimal
                        return round($pct, 2);
                    })
                    ->suffix('%')
                    ->badge()
                    ->color(function ($state) {
                        // Colorea según el %
                        $v = (float) $state;
                        return $v === 0.0 ? 'gray'
                            : ($v >= 70 ? 'success'
                                : ($v >= 40 ? 'warning' : 'danger'));
                    })
                    ->sortable(query: function (Builder $q, string $dir) {
                        // Ordena por ((confirmadas + vendidas) / produccion) * 100, manejando nulos/cero
                        $expr = <<<SQL
CASE
  WHEN COALESCE(aproduccion_count, 0) = 0 THEN 0
  ELSE ( (COALESCE(confirmadas_count,0) + COALESCE(vendidas_count,0)) * 100.0 / COALESCE(aproduccion_count,1) )
END
SQL;
                        $q->orderByRaw("$expr $dir");
                    }),

            ])
            ->filters([])
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
        $tz = config('app.timezone', 'UTC');

        // Hoy en tu zona horaria
        $today = Carbon::today($tz);

        // Primer día del mes anterior (límite mínimo para seguir mostrándola)
        $firstDayPreviousMonth = $today->copy()
            ->subMonth()
            ->startOfMonth()
            ->toDateString(); // formato 'Y-m-d'

        return User::query()
            ->select('users.*')
            ->role(['teleoperator', 'head_of_room'])
            ->where(function (Builder $q) use ($firstDayPreviousMonth) {
                $q->whereNull('users.baja') // sin baja, siempre visible
                    // solo mostrar si la baja fue en el mes actual o en el mes anterior
                    ->orWhereDate('users.baja', '>=', $firstDayPreviousMonth);
            })
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
