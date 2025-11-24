<?php

namespace App\Filament\Gerente\Pages;

use App\Models\User;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Builder;

class ComercialesResumenHoy extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Resumen diarios comerciales';
    protected static ?string $title = 'Resumen diario de comerciales';
    protected static ?string $slug = 'comerciales-resumen-hoy';
    protected static ?int $navigationSort = 11;

    protected static string $view = 'filament.gerente.pages.comerciales-resumen-hoy';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->role('commercial') // solo comerciales
                    ->whereNull('baja')
            )
            ->columns([
                Tables\Columns\TextColumn::make('empleado_id')
                    ->label('Empleado ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre completo')
                    ->formatStateUsing(fn(User $record) => trim(
                        $record->name . ' ' . ($record->last_name ?? '')
                    ))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_resumen')
                    ->label('Ver resumen de hoy')
                    ->button()
                    ->outlined()
                    ->color('primary')
                    ->url(fn(User $record) => \App\Filament\Gerente\Pages\ResumenNotasComercialHoy::getUrl(
                        ['comercial_id' => $record->id],
                        panel: 'gerente'
                    ))
                    ->openUrlInNewTab(false),
            ])
            ->striped()
            ->paginated(true)
            ->defaultPaginationPageOption(25)
            ->defaultSort('name');
    }
}
