<?php

namespace App\Filament\SuperAdmin\Resources\DuplicadosResource\Widgets;

use App\Models\Customer;
use App\Models\Scopes\NotMergedScope;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class FusionadosWidget extends BaseWidget
{
    protected static ?string $heading = 'Registros ya fusionados';

    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return Customer::withoutGlobalScope(NotMergedScope::class)
            ->whereNotNull('merged_into_id')
            ->with([
                'mergedInto:id,first_names,last_names',
                'mergedBy:id,name,last_name,empleado_id',
            ])
            ->orderByRaw('CASE WHEN merged_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('merged_at')
            ->orderByDesc('id');
    }

    public function table(Table $table): Table
    {
        $fmt = fn (?string $p): string => $p
            ? implode(' ', str_split(preg_replace('/\D+/', '', $p), 3))
            : '—';

        return $table
            ->query($this->getTableQuery())
            ->paginated([25, 50, 100])
            ->defaultSort('merged_at', 'desc')
            ->columns([
                TextColumn::make('fecha_fusion')
                    ->label('Fecha Fusión')
                    ->getStateUsing(fn (Customer $r): string => optional($r->merged_at)?->format('d/m/Y') ?? '—')
                    ->sortable(query: function (Builder $query, string $direction): void {
                        $query->reorder()
                            ->orderByRaw('CASE WHEN merged_at IS NULL THEN 1 ELSE 0 END')
                            ->orderBy('merged_at', $direction)
                            ->orderBy('id', $direction);
                    })
                    ->badge()
                    ->color(Color::Indigo),

                TextColumn::make('hora_fusion')
                    ->label('Hora')
                    ->getStateUsing(fn (Customer $r): string => optional($r->merged_at)?->format('H:i') ?? '—')
                    ->alignCenter(),

                TextColumn::make('nombre_cliente')
                    ->label('Nombre del Cliente')
                    ->getStateUsing(fn (Customer $r) => mb_strtoupper(trim($r->first_names.' '.$r->last_names)))
                    ->weight(FontWeight::Bold)
                    ->color(Color::Red)
                    ->extraAttributes(['class' => 'whitespace-nowrap']),

                TextColumn::make('dni')
                    ->label('DNI')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('id')
                    ->label('ID Cliente')
                    ->badge()
                    ->color(Color::Gray)
                    ->sortable(),

                TextColumn::make('phone')
                    ->label('Teléfono 1')
                    ->getStateUsing(fn (Customer $r) => $fmt($r->phone))
                    ->color(Color::Amber),

                TextColumn::make('secondary_phone')
                    ->label('Teléfono 2')
                    ->getStateUsing(fn (Customer $r) => $fmt($r->secondary_phone))
                    ->color(Color::Amber),

                TextColumn::make('third_phone')
                    ->label('Teléfono 3')
                    ->getStateUsing(fn (Customer $r) => $fmt($r->third_phone))
                    ->color(Color::Amber),

                TextColumn::make('phone1_commercial')
                    ->label('Tel. Comercial 1')
                    ->getStateUsing(fn (Customer $r) => $fmt($r->phone1_commercial))
                    ->color(Color::Teal),

                TextColumn::make('phone2_commercial')
                    ->label('Tel. Comercial 2')
                    ->getStateUsing(fn (Customer $r) => $fmt($r->phone2_commercial))
                    ->color(Color::Teal),

                TextColumn::make('fusionado_en')
                    ->label('Fusionado en')
                    ->getStateUsing(function (Customer $r): string {
                        $keeper = $r->mergedInto;
                        if (! $keeper) {
                            return '—';
                        }
                        $name = mb_strtoupper(trim($keeper->first_names.' '.$keeper->last_names));

                        return "#{$keeper->id} — {$name}";
                    })
                    ->weight(FontWeight::Bold)
                    ->color(Color::Green)
                    ->extraAttributes(['class' => 'whitespace-nowrap']),

                TextColumn::make('fusionado_por')
                    ->label('Fusionado por')
                    ->getStateUsing(function (Customer $r): string {
                        $user = $r->mergedBy;
                        if (! $user) {
                            return '—';
                        }

                        return trim($user->name.' '.$user->last_name);
                    }),
            ])
            ->filters([])
            ->actions([])
            ->bulkActions([]);
    }
}
