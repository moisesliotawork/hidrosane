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
use App\Filament\Gerente\Pages\DeclaracionesComercialDetalleAyer;

class DeclaracionesComercialesAyer extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = null; //Sin icono
    protected static ?string $navigationLabel = null; //No aparece en menú
    protected static bool $shouldRegisterNavigation = false; // No sale en la barra

    protected static string $view = 'filament.gerente.pages.declaraciones-comerciales-ayer';
    protected static ?string $slug = 'declaraciones-comerciales-ayer';


    public function getTitle(): string
    {
        return 'REPORTES DE AYER';
    }

    public function table(Table $table): Table
    {
        $yesterday = now()->subDay()->toDateString();

        return $table
            ->query(
                User::query()
                    ->role(['commercial', 'team_leader', 'sales_manager'])
                    ->where(function (Builder $q) use ($yesterday) {
                        $q->whereNull('fecha_baja')
                            ->orWhereDate('fecha_baja', '>', $yesterday);
                    })
                    ->withCount([
                        'notasDeclaradas as oficina_count' => fn(Builder $q) =>
                            $q->whereDate('fecha_declaracion', $yesterday)
                                ->where('estado_terminal', EstadoTerminal::SALA->value),

                        'notasDeclaradas as nulos_count' => fn(Builder $q) =>
                            $q->whereDate('fecha_declaracion', $yesterday)
                                ->where('estado_terminal', EstadoTerminal::NUL->value),

                        'notasDeclaradas as ausentes_count' => fn(Builder $q) =>
                            $q->whereDate('fecha_declaracion', $yesterday)
                                ->where('estado_terminal', EstadoTerminal::AUSENTE->value),

                        'notasDeclaradas as confirmadas_count' => fn(Builder $q) =>
                            $q->whereDate('fecha_declaracion', $yesterday)
                                ->where('estado_terminal', EstadoTerminal::CONFIRMADO->value),

                        'notasDeclaradas as ventas_count' => fn(Builder $q) =>
                            $q->whereDate('fecha_declaracion', $yesterday)
                                ->where('estado_terminal', EstadoTerminal::VENTA->value),
                    ])
            )
            ->columns([
                Tables\Columns\TextColumn::make('empleado_id')
                    ->label('Id-emp')
                    ->sortable(),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Comercial')
                    ->getStateUsing(fn(User $record) => trim($record->name . ' ' . $record->last_name)),

                Tables\Columns\TextColumn::make('oficina_count')->label('Of')->badge()->color('pink'),
                Tables\Columns\TextColumn::make('nulos_count')->label('Nulo')->badge()->color('danger'),
                Tables\Columns\TextColumn::make('ausentes_count')->label('Ause')->badge()->color('info'),
                Tables\Columns\TextColumn::make('confirmadas_count')->label('Conf')->badge()->color('orange'),
                Tables\Columns\TextColumn::make('ventas_count')->label('Vta')->badge()->color('success'),
            ])
            ->defaultSort('name', 'asc')
            ->defaultSort('last_name', 'asc')
            ->recordUrl(
                fn(User $record) =>
                DeclaracionesComercialDetalleAyer::getUrl(['record' => $record])
            );

    }
}
