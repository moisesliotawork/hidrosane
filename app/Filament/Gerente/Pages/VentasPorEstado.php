<?php

namespace App\Filament\Gerente\Pages;

use App\Enums\EstadoVenta;
use App\Models\Venta;
use Filament\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Illuminate\Support\Facades\DB; // ⬅️ importa DB aquí

class VentasPorEstado extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Supervision de Estados de Contratos';
    protected static ?string $title = 'Supervision de Estados de Contratos';
    protected static ?string $slug = 'ventas-por-estado';
    protected static string $view = 'filament.gerente.pages.ventas-por-estado';

    public function table(Table $table): Table
    {
        // 1) Todos los estados del enum + bucket para NULL
        $allStates = array_map(fn($c) => $c->value, EstadoVenta::cases());
        $allStates[] = '__NULL__';

        // 2) UNION ALL con todos los estados (Query\Builder)
        $estadosQuery = null;
        foreach ($allStates as $value) {
            $q = DB::query()->selectRaw('? as estado_raw', [$value]);
            $estadosQuery = $estadosQuery ? $estadosQuery->unionAll($q) : $q;
        }

        // 3) Conteo real por estado (Eloquent\Builder) – mapear NULL a '__NULL__'
        $conteoSub = Venta::query()
            ->selectRaw('COALESCE(estado_venta, "__NULL__") AS estado_raw, COUNT(*) AS total')
            ->groupByRaw('COALESCE(estado_venta, "__NULL__")');

        // 4) Builder final (Eloquent) usando alias `estado_raw` para evitar el cast
        $builder = Venta::query()
            ->fromSub($estadosQuery, 'estados')
            ->leftJoinSub($conteoSub, 'v', 'v.estado_raw', '=', 'estados.estado_raw')
            ->selectRaw('estados.estado_raw, COALESCE(v.total, 0) AS total');

        return $table
            ->query($builder) // Eloquent\Builder ✅
            ->columns([
                TextColumn::make('row')
                    ->label('ID')
                    ->rowIndex(),

                TextColumn::make('estado_raw')
                    ->label('Estado de la venta')
                    ->formatStateUsing(function ($state): string {
                        // $state es string (por el alias) o '__NULL__'
                        if ($state === '__NULL__' || $state === null || $state === '') {
                            return 'En blanco';
                        }
                        return EstadoVenta::from($state)->label();
                    })
                    ->badge()
                    ->color(function ($state): string {
                        if ($state === '__NULL__' || $state === null || $state === '') {
                            return 'gray';
                        }
                        return EstadoVenta::from($state)->color();
                    })
                    ->sortable(),

                TextColumn::make('total')
                    ->label('Nº de Contratos')
                    ->numeric()
                    ->sortable()
                    ->summarize(Sum::make()->label('TOTAL')),
            ])
            ->defaultSort('total', 'desc')
            ->paginated(false)
            ->striped();
    }

    /** Clave única por fila para tablas sin PK (GROUP/UNION). */
    public function getTableRecordKey(mixed $record): string
    {
        // Usar el alias estado_raw para evitar el cast al enum
        $state = is_array($record)
            ? ($record['estado_raw'] ?? null)
            : data_get($record, 'estado_raw');

        return 'estado-' . ($state ?? '__NULL__');
    }
}
