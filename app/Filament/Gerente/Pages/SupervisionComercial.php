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

class SupervisionComercial extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-eye';
    protected static ?string $navigationLabel = 'Supervision Comercial';
    protected static ?string $title = 'SUPERVISION COMERCIAL';

    protected static string $view = 'filament.gerente.pages.supervision-comercial';

    public function table(Table $table): Table
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        return $table
            ->query(
                User::query()
                    ->role(['commercial', 'team_leader', 'sales_manager'])
                    ->where(function (Builder $q) use ($today) {
                        $q->whereNull('baja')
                            ->orWhereDate('baja', '>', $today);
                    })
                    ->withCount([
                        // =========================
                        // CONFIRMADAS
                        // =========================
                        'notasDeclaradas as conf_ayer' => fn(Builder $q) =>
                            $q->whereDate('fecha_declaracion', $yesterday)
                                ->where('estado_terminal', EstadoTerminal::CONFIRMADO->value),

                        'notasDeclaradas as conf_hoy' => fn(Builder $q) =>
                            $q->whereDate('fecha_declaracion', $today)
                                ->where('estado_terminal', EstadoTerminal::CONFIRMADO->value),

                        'notasDeclaradas as conf_mes' => fn(Builder $q) =>
                            $q->whereBetween('fecha_declaracion', [$monthStart, $monthEnd])
                                ->where('estado_terminal', EstadoTerminal::CONFIRMADO->value),

                        // =========================
                        // NULAS
                        // =========================
                        'notasDeclaradas as nul_ayer' => fn(Builder $q) =>
                            $q->whereDate('fecha_declaracion', $yesterday)
                                ->where('estado_terminal', EstadoTerminal::NUL->value),

                        'notasDeclaradas as nul_hoy' => fn(Builder $q) =>
                            $q->whereDate('fecha_declaracion', $today)
                                ->where('estado_terminal', EstadoTerminal::NUL->value),

                        'notasDeclaradas as nul_mes' => fn(Builder $q) =>
                            $q->whereBetween('fecha_declaracion', [$monthStart, $monthEnd])
                                ->where('estado_terminal', EstadoTerminal::NUL->value),

                        // =========================
                        // OFICINA (SALA)
                        // =========================
                        'notasDeclaradas as of_ayer' => fn(Builder $q) =>
                            $q->whereDate('fecha_declaracion', $yesterday)
                                ->where('estado_terminal', EstadoTerminal::SALA->value),

                        'notasDeclaradas as of_hoy' => fn(Builder $q) =>
                            $q->whereDate('fecha_declaracion', $today)
                                ->where('estado_terminal', EstadoTerminal::SALA->value),

                        'notasDeclaradas as of_mes' => fn(Builder $q) =>
                            $q->whereBetween('fecha_declaracion', [$monthStart, $monthEnd])
                                ->where('estado_terminal', EstadoTerminal::SALA->value),

                        // (Opcional) VENTAS
                        'notasDeclaradas as vta_ayer' => fn(Builder $q) =>
                            $q->whereDate('fecha_declaracion', $yesterday)
                                ->where('estado_terminal', EstadoTerminal::VENTA->value),

                        'notasDeclaradas as vta_hoy' => fn(Builder $q) =>
                            $q->whereDate('fecha_declaracion', $today)
                                ->where('estado_terminal', EstadoTerminal::VENTA->value),

                        'notasDeclaradas as vta_mes' => fn(Builder $q) =>
                            $q->whereBetween('fecha_declaracion', [$monthStart, $monthEnd])
                                ->where('estado_terminal', EstadoTerminal::VENTA->value),
                    ])
            )
            ->columns([
                Tables\Columns\TextColumn::make('empleado_id')
                    ->label('Id-emp')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Comercial')
                    ->getStateUsing(fn(User $record) => trim($record->name . ' ' . $record->last_name))
                    ->searchable(['name', 'last_name'])
                    ->sortable(),

                // ===== CONF =====
                Tables\Columns\TextColumn::make('conf_ayer')
                    ->label('Ayer Conf')
                    ->badge()
                    ->color('orange')
                    ->sortable()
                    ->formatStateUsing(fn($state) => $state ?? 0),

                Tables\Columns\TextColumn::make('conf_hoy')
                    ->label('Hoy Conf')
                    ->badge()
                    ->color('orange')
                    ->sortable()
                    ->formatStateUsing(fn($state) => $state ?? 0),

                Tables\Columns\TextColumn::make('conf_mes')
                    ->label('Mes Conf')
                    ->badge()
                    ->color('orange')
                    ->sortable()
                    ->formatStateUsing(fn($state) => $state ?? 0),

                // ===== NUL =====
                Tables\Columns\TextColumn::make('nul_ayer')
                    ->label('Ayer Nul')
                    ->badge()
                    ->color('danger')
                    ->sortable()
                    ->formatStateUsing(fn($state) => $state ?? 0),

                Tables\Columns\TextColumn::make('nul_hoy')
                    ->label('Hoy Nul')
                    ->badge()
                    ->color('danger')
                    ->sortable()
                    ->formatStateUsing(fn($state) => $state ?? 0),

                Tables\Columns\TextColumn::make('nul_mes')
                    ->label('Mes Nul')
                    ->badge()
                    ->color('danger')
                    ->sortable()
                    ->formatStateUsing(fn($state) => $state ?? 0),

                // ===== OFICINA =====
                Tables\Columns\TextColumn::make('of_ayer')
                    ->label('Ayer Of')
                    ->badge()
                    ->color('pink')
                    ->sortable()
                    ->formatStateUsing(fn($state) => $state ?? 0),

                Tables\Columns\TextColumn::make('of_hoy')
                    ->label('Hoy Of')
                    ->badge()
                    ->color('pink')
                    ->sortable()
                    ->formatStateUsing(fn($state) => $state ?? 0),

                Tables\Columns\TextColumn::make('of_mes')
                    ->label('Mes Of')
                    ->badge()
                    ->color('pink')
                    ->sortable()
                    ->formatStateUsing(fn($state) => $state ?? 0),

                // (Opcional) VENTAS
                Tables\Columns\TextColumn::make('vta_ayer')
                    ->label('Ayer Vta')
                    ->badge()
                    ->color('success')
                    ->sortable()
                    ->formatStateUsing(fn($state) => $state ?? 0),

                Tables\Columns\TextColumn::make('vta_hoy')
                    ->label('Hoy Vta')
                    ->badge()
                    ->color('success')
                    ->sortable()
                    ->formatStateUsing(fn($state) => $state ?? 0),

                Tables\Columns\TextColumn::make('vta_mes')
                    ->label('Mes Vta')
                    ->badge()
                    ->color('success')
                    ->sortable()
                    ->formatStateUsing(fn($state) => $state ?? 0),
            ])
            ->defaultSort('name', 'asc');
    }
}
