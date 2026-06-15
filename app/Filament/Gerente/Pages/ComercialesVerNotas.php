<?php

namespace App\Filament\Gerente\Pages;

use App\Models\User;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;

class ComercialesVerNotas extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Reasignar Visitas';
    protected static ?string $title = 'Reasignar Visitas';
    protected static ?string $slug = 'comerciales-ver-notas';
    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.gerente.pages.comerciales-ver-notas';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->role(['commercial', 'team_leader', 'sales_manager'])
                    ->whereNull('baja')
                    ->withCount([
                        'notasDeclaradas as notas_hoy_count' => fn ($query) => $query
                            ->forReasignarVisitas()
                            ->whereDate('assignment_date', today()),
                        'notasDeclaradas as notas_anteriores_count' => fn ($query) => $query
                            ->forReasignarVisitas()
                            ->whereDate('assignment_date', '<', today())
                            ->whereDate('assignment_date', '>=', now()->subDays(5)->startOfDay()),
                    ])
            )
            ->columns([
                Tables\Columns\TextColumn::make('empleado_id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->formatStateUsing(fn ($record) => trim($record->name . ' ' . ($record->last_name ?? '')))
                    ->searchable(),

                Tables\Columns\TextColumn::make('notas_hoy_count')
                    ->label('Notas Hoy')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn ($state) => (int) $state > 0 ? (string) (int) $state : null)
                    ->placeholder('—')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('notas_anteriores_count')
                    ->label('Anteriores')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => (int) $state > 0 ? (string) (int) $state : null)
                    ->placeholder('—')
                    ->alignCenter(),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_notas')
                    ->label('Ver notas')
                    ->button()
                    ->outlined()
                    ->color('primary')
                    ->url(fn ($record) => \App\Filament\Gerente\Pages\NotasDeComercial::getUrl(
                        ['comercial_id' => $record->id],
                        panel: 'gerente'
                    ))
                    ->openUrlInNewTab(false),
            ])
            ->headerActions([
                Tables\Actions\Action::make('ver_reten')
                    ->label('Notas RETEN')
                    ->button()
                    ->color('orange')
                    ->icon('heroicon-o-document-text')
                    ->url(fn () => \App\Filament\Gerente\Pages\NotasDeComercial::getUrl(
                        ['comercial_id' => 'reten'],
                        panel: 'gerente'
                    ))
                    ->openUrlInNewTab(false),
            ])
            ->striped()
            ->paginated(true)
            ->defaultPaginationPageOption(25)
            ->defaultSort('empleado_id');
    }
}
