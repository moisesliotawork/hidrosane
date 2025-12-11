<?php

namespace App\Filament\Gerente\Pages;

use App\Enums\EstadoTerminal;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Action;
use App\Filament\Gerente\Pages\DeclaracionesComercialDetalleHoy;


class DeclaracionesComercialesHoy extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Reportes De Hoy';

    protected static string $view = 'filament.gerente.pages.declaraciones-comerciales-hoy';

    public function getTitle(): string
    {
        return 'REPORTES DE HOY';
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('ver_ayer')
                ->label('Resumen de Ayer')
                ->color('success')
                ->icon('heroicon-o-calendar-days')
                ->url(fn() => route('filament.gerente.pages.declaraciones-comerciales-ayer'))
        ];
    }


    public function table(Table $table): Table
    {
        $today = now()->toDateString();

        return $table
            ->query(
                User::query()
                    ->role(['commercial', 'team_leader', 'sales_manager'])
                    ->withCount([
                        // OFICINA  (EstadoTerminal::SALA)
                        'notasDeclaradas as oficina_count' => fn(Builder $q) =>
                            $q->whereDate('fecha_declaracion', $today)
                                ->where('estado_terminal', EstadoTerminal::SALA->value),

                        // NULO
                        'notasDeclaradas as nulos_count' => fn(Builder $q) =>
                            $q->whereDate('fecha_declaracion', $today)
                                ->where('estado_terminal', EstadoTerminal::NUL->value),

                        // AUSENTE
                        'notasDeclaradas as ausentes_count' => fn(Builder $q) =>
                            $q->whereDate('fecha_declaracion', $today)
                                ->where('estado_terminal', EstadoTerminal::AUSENTE->value),

                        // CONFIRMADA
                        'notasDeclaradas as confirmadas_count' => fn(Builder $q) =>
                            $q->whereDate('fecha_declaracion', $today)
                                ->where('estado_terminal', EstadoTerminal::CONFIRMADO->value),

                        // VENTA
                        'notasDeclaradas as ventas_count' => fn(Builder $q) =>
                            $q->whereDate('fecha_declaracion', $today)
                                ->where('estado_terminal', EstadoTerminal::VENTA->value),
                    ])
            )
            ->columns([
                // Empleado ID
                Tables\Columns\TextColumn::make('empleado_id')
                    ->label('Id-emp')
                    ->sortable()
                    ->searchable(),

                // Nombre completo
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Comercial')
                    ->getStateUsing(fn(User $record) => trim($record->name . ' ' . $record->last_name))
                    ->searchable(['name', 'last_name']),

                // OFICINA - rosa (pink)
                Tables\Columns\TextColumn::make('oficina_count')
                    ->label('Of')
                    ->badge()
                    ->color('pink')
                    ->sortable()
                    ->formatStateUsing(fn($state) => $state ?? 0),

                // NULO - rojo (danger)
                Tables\Columns\TextColumn::make('nulos_count')
                    ->label('Nulo')
                    ->badge()
                    ->color('danger')
                    ->sortable()
                    ->formatStateUsing(fn($state) => $state ?? 0),

                // AUSENTE - azul (info)
                Tables\Columns\TextColumn::make('ausentes_count')
                    ->label('Auste')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->formatStateUsing(fn($state) => $state ?? 0),

                // CONFIRMADA - naranja (warning)
                Tables\Columns\TextColumn::make('confirmadas_count')
                    ->label('Conf')
                    ->badge()
                    ->color('orange')
                    ->sortable()
                    ->formatStateUsing(fn($state) => $state ?? 0),

                // VENTA - verde (success)
                Tables\Columns\TextColumn::make('ventas_count')
                    ->label('Vta')
                    ->badge()
                    ->color('success')
                    ->sortable()
                    ->formatStateUsing(fn($state) => $state ?? 0),
            ])
            ->defaultSort('name', 'asc')
            ->defaultSort('last_name', 'asc')
            ->recordUrl(
                fn(User $record) =>
                DeclaracionesComercialDetalleHoy::getUrl(['record' => $record])
            );

    }
}
