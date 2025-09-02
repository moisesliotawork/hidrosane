<?php

namespace App\Filament\Repartidor\Resources;

use App\Filament\Repartidor\Resources\MisNumerosResource\Pages;
use App\Models\Venta;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class MisNumerosResource extends Resource
{
    protected static ?string $model = Venta::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationLabel = 'Mis Numeros';
    protected static ?string $pluralModelLabel = 'Mis Numeros';
    protected static ?string $modelLabel = 'Mis Numeros';
    protected static ?string $breadcrumb = 'Mis Numeros';
    protected static ?string $slug = 'mis-numeros';

    /** No usaremos formularios en este resource */
    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(fn() => static::getEloquentQuery())
            ->columns([
                TextColumn::make('nro_contr_adm')
                    ->label('CONTRATO')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('CLIENTE')
                    ->state(
                        fn(Venta $r) =>
                        $r->customer?->full_name
                        ?? $r->customer?->name
                        ?? ($r->customer?->nombre ?? '-')
                    )
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('customer', function (Builder $q) use ($search) {
                            $q->where('first_names', 'like', "%{$search}%")
                                ->orWhere('last_names', 'like', "%{$search}%")
                                ->orWhereRaw("CONCAT(first_names,' ',last_names) LIKE ?", ["%{$search}%"]);
                        });
                    }),

                TextColumn::make('fecha_entrega')
                    ->label('F. ENTR')
                    ->date('Y-m-d')
                    ->sortable(),

                TextColumn::make('reparto.fecha_devolucion')
                    ->label('F ANUL')
                    ->date('Y-m-d')
                    ->sortable(),

                // Códigos comerciales y repartidores
                TextColumn::make('c1')
                    ->label('c1')
                    ->state(fn(Venta $r) => $r->comercial->code
                        ?? $r->comercial->codigo
                        ?? ($r->comercial_id ?? '-')),

                TextColumn::make('c2')
                    ->label('c2')
                    ->state(fn(Venta $r) => optional($r->companion)->code
                        ?? optional($r->companion)->codigo
                        ?? ($r->companion_id ?? '-')),

                TextColumn::make('r1')
                    ->label('R1')
                    ->state(fn(Venta $r) => $r->repartidor->code
                        ?? $r->repartidor->codigo
                        ?? ($r->repartidor_id ?? '-')),

                TextColumn::make('r2')
                    ->label('R2')
                    ->state(function (Venta $r) {
                        // Prioriza mostrar el "código" del usuario; si no existe, muestra el ID
                        return $r->repartidor2->code
                            ?? $r->repartidor2->codigo
                            ?? ($r->repartidor_2 ?? '-');
                    })
                    ->sortable(),

                // Estado (usa tus accessors label/color si existen)
                TextColumn::make('estado_venta')
                    ->label('ESTADO')
                    ->badge()
                    ->state(fn(Venta $r) => $r->estado_venta_label ?? (string) $r->estado_venta?->name ?? '')
                    ->color(fn(Venta $r) => $r->estado_venta_color ?? 'gray'),

                // Importes
                TextColumn::make('importe_comercial')
                    ->label('imp com')
                    ->numeric(decimalPlaces: 2, thousandsSeparator: '.', decimalSeparator: ','),

                TextColumn::make('importe_repartidor')
                    ->label('IMP REP')
                    ->numeric(decimalPlaces: 2, thousandsSeparator: '.', decimalSeparator: ','),

                // Ventas del repartidor
                TextColumn::make('vta_rep')->label('VTA REP'),
                TextColumn::make('vta_esp')->label('VTA ESP'),
                TextColumn::make('vta_ac')->label('VTA AC'),

                // Comisiones
                TextColumn::make('com_venta')
                    ->label('COM VENTA')
                    ->numeric(decimalPlaces: 2, thousandsSeparator: '.', decimalSeparator: ','),

                TextColumn::make('com_entrega')
                    ->label('COMentr')
                    ->numeric(decimalPlaces: 2, thousandsSeparator: '.', decimalSeparator: ','),

                TextColumn::make('com_conpago')
                    ->label('ConPago')
                    ->numeric(decimalPlaces: 2, thousandsSeparator: '.', decimalSeparator: ','),

                // Pases de puntos
                TextColumn::make('pas_comercial')->label('PAS c'),
                TextColumn::make('pas_repartidor')->label('PASR'),
            ])
            ->defaultSort('nro_contrato', 'desc')
            ->filters([
                // agrega filtros si los necesitas
            ])
            ->actions([
                // Sin acciones de ver/editar/eliminar
            ])
            ->bulkActions([
                // Sin acciones masivas
            ]);
    }

    /** Filtra por el repartidor autenticado (panel Repartidor) */
    public static function getEloquentQuery(): Builder
    {
        $userId = auth()->id();
        return parent::getEloquentQuery()
            ->with(['customer', 'comercial', 'companion', 'repartidor', 'reparto'])
            ->when($userId, fn($q) => $q->where('repartidor_id', $userId));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMisNumeros::route('/'),
        ];
    }
}
