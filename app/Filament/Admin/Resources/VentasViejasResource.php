<?php

namespace App\Filament\Admin\Resources;

use App\Enums\EstadoReparto;
use App\Filament\Admin\Resources\VentasViejasResource\Pages;
use App\Filament\Admin\Resources\VentasViejasResource\RelationManagers;
use App\Models\Venta;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use App\Enums\EstadoVenta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use App\Enums\EstadoEntrega;
use App\Models\Reparto;
use App\Models\User;
use App\Models\{Producto, Oferta};
use Filament\Forms\Components\{Section, Repeater, Grid, TextInput, Hidden};
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Enums\VendidoPor;


class VentasViejasResource extends Resource
{
    protected static ?string $model = Venta::class;

    protected static ?string $navigationLabel = 'Ventas Anteriores';
    protected static ?string $modelLabel = 'Venta Anterior';
    protected static ?string $pluralModelLabel = 'Ventas Anteriores';
    protected static ?string $breadcrumb = 'Ventas Anteriores';
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

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
            ->modifyQueryUsing(function (Builder $query) {
                $corte = Carbon::parse('2025-11-01')->startOfDay();

                //0) Excluir ventas que ya tengan reparto
                $query->whereDoesntHave('reparto');

                // 1) Solo ventas anteriores al mes requerido
                $query->where(function ($q) use ($corte) {
                    $q->whereDate('fecha_venta', '<', $corte)
                        ->orWhere(function ($q2) use ($corte) {
                            $q2->whereNull('fecha_venta')
                                ->whereDate('created_at', '<', $corte);
                        });
                });

                // 2) Excluir contratos con -B
                $query->where(function ($q) {
                    $q->whereNull('nro_contr_adm')
                        ->orWhere('nro_contr_adm', '=', '')
                        ->orWhere('nro_contr_adm', 'not like', '%-B%');
                });
            })

            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('nro_contr_adm')->label('Nº Contrato')->sortable()->searchable(),

                // Mantenemos la visualización de "-B" pero sin URL ni acciones
                TextColumn::make('contrato_b')
                    ->label('-B')
                    ->state(function (Venta $r) {
                        $b = method_exists($r, 'contratoB')
                            ? $r->contratoB()
                            : $r->asociadas()->where('nro_contr_adm', 'like', '%-B')->first();
                        return $b?->nro_contr_adm ?? '—';
                    })
                    ->sortable(false)
                    ->searchable(false),

                TextColumn::make('note.nro_nota')->label('Nº Nota')->sortable()->searchable(),

                Tables\Columns\TextColumn::make('estado_venta')
                    ->badge()
                    ->color(fn(EstadoVenta $state): string => $state->color())
                    ->formatStateUsing(fn(EstadoVenta $state): string => $state->label())
                    ->sortable()
                    ->label('ESTADO/CONTR'),

                TextColumn::make('nro_cliente_adm')->label('Nº Cliente')->searchable()->sortable(),

                TextColumn::make('customer.name')
                    ->label('Nombre')
                    ->searchable(['first_names', 'last_names'])
                    ->sortable(),

                TextColumn::make('fecha_venta')->label('Fecha venta')->date('d/m/Y')->sortable(),

                TextColumn::make('hora_venta')
                    ->label('Hora')
                    ->state(fn(Venta $r) => optional($r->fecha_venta)->format('H:i'))
                    ->sortable(),

                TextColumn::make('comercial.empleado_id')
                    ->label('Comercial')
                    ->state(function (Venta $r) {
                        $u = $r->comercial;
                        return $u ? "{$u->empleado_id} - {$u->name} {$u->last_name}" : null;
                    })
                    ->formatStateUsing(fn($state) => $state ?: '--')
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('comercial', function ($q) use ($search) {
                            $q->where('empleado_id', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(query: function ($query, string $direction) {
                        $query->leftJoin('users as com', 'com.id', '=', 'ventas.comercial_id')
                            ->orderBy('com.empleado_id', $direction)
                            ->select('ventas.*');
                    }),

                TextColumn::make('companion.empleado_id')
                    ->label('Compañero')
                    ->state(function (Venta $r) {
                        $u = $r->companion;
                        return $u ? "{$u->empleado_id} - {$u->name} {$u->last_name}" : null;
                    })
                    ->formatStateUsing(fn($state) => $state ?: '--')
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('companion', function ($q) use ($search) {
                            $q->where('empleado_id', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(query: function ($query, string $direction) {
                        $query->leftJoin('users as comp', 'comp.id', '=', 'ventas.companion_id')
                            ->orderBy('comp.empleado_id', $direction)
                            ->select('ventas.*');
                    }),

                TextColumn::make('fecha_entrega')->label('F. repartidor')->date('d/m/Y'),
                TextColumn::make('horario_entrega')->label('Horario rep.'),
                TextColumn::make('customer.primary_address')
                    ->label('Dirección')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),
            ])
            ->actions([
                Action::make('entrega_simple')
                    ->label('Entrega simple')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->form([
                        Select::make('repartidor_id')
                            ->label('Repartidor')
                            ->options(
                                fn() =>
                                User::role('delivery')
                                    ->orderBy('empleado_id')
                                    ->get()
                                    ->mapWithKeys(fn($u) => [$u->id => "{$u->empleado_id} - {$u->name} {$u->last_name}"])
                                    ->all()
                            )
                            ->searchable()
                            ->native(false)
                            ->required(),
                    ])
                    ->action(function (Venta $record, array $data) {
                        DB::transaction(function () use ($record, $data) {
                            // 1) Asignar repartidor a la venta
                            $record->update([
                                'repartidor_id' => $data['repartidor_id'],
                            ]);

                            // 2) Crear / actualizar Reparto con estado_entrega = COMPLETO
                            $reparto = Reparto::firstOrNew(['venta_id' => $record->id]);
                            $reparto->estado_entrega = EstadoEntrega::COMPLETO;
                            $reparto->estado = EstadoReparto::ENTREGA_SIMPLE;
                            // (Opcional) setear flags por defecto
                            $reparto->cliente_firma_garantias = $reparto->cliente_firma_garantias ?? false;
                            $reparto->cliente_comentario_goodwork = $reparto->cliente_comentario_goodwork ?? false;
                            $reparto->cliente_firma_digital = $reparto->cliente_firma_digital ?? false;
                            $reparto->save();

                            // 3) Cantidad entregada = cantidad en cada línea de productos
                            $record->loadMissing('ventaOfertas.productos');
                            foreach ($record->ventaOfertas as $oferta) {
                                foreach ($oferta->productos as $linea) {
                                    // evita eventos ruidosos
                                    $linea->updateQuietly([
                                        'cantidad_entregada' => (int) $linea->cantidad,
                                    ]);
                                }
                            }
                        });

                        Notification::make()
                            ->title('Entrega simple registrada')
                            ->body('Se asignó el repartidor y se marcó la entrega como completa.')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Registrar entrega simple')
                    ->modalSubmitActionLabel('Guardar'),

                Action::make('entrega_con_venta')
                    ->label('Entrega con venta')
                    ->icon('heroicon-m-clipboard-document-check')
                    ->color('primary')
                    ->modalWidth('5xl')
                    ->form([
                        Select::make('repartidor_id')
                            ->label('Repartidor')
                            ->options(
                                fn() =>
                                User::role('delivery')
                                    ->orderBy('empleado_id')
                                    ->get()
                                    ->mapWithKeys(fn($u) => [$u->id => "{$u->empleado_id} - {$u->name} {$u->last_name}"])
                                    ->all()
                            )
                            ->searchable()
                            ->native(false)
                            ->required(),

                        // 📦 Ofertas del repartidor
                        Section::make('Agregar ofertas del repartidor')
                            ->schema([
                                Repeater::make('ofertas_repartidor')
                                    ->label(false)
                                    ->columns(1)
                                    ->defaultItems(1)
                                    ->collapsible()
                                    ->createItemButtonLabel('Añadir oferta')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            Select::make('oferta_id')
                                                ->label('Oferta')
                                                ->options(fn() => Oferta::query()
                                                    ->orderBy('nombre')
                                                    ->pluck('nombre', 'id'))
                                                ->required()
                                                ->reactive()
                                                ->searchable()
                                                ->preload(),

                                            TextInput::make('puntos')
                                                ->numeric()
                                                ->disabled()
                                                ->dehydrated(false),
                                        ]),

                                        // 🧩 Productos dentro de la oferta
                                        Repeater::make('productos')
                                            ->minItems(1)
                                            ->defaultItems(1)
                                            ->columns(1)
                                            ->afterStateUpdated(function (Get $get, Set $set) {
                                                $total = collect($get('productos') ?? [])
                                                    ->sum(fn($l) => (int) ($l['puntos_linea'] ?? 0));
                                                $set('puntos', $total);
                                            })
                                            ->schema([
                                                Grid::make(3)->schema([
                                                    Hidden::make('vendido_por')
                                                        ->default(VendidoPor::Repartidor->value),

                                                    Select::make('producto_id')
                                                        ->label('Producto')
                                                        ->options(fn() => Producto::query()
                                                            ->where('delete', false)
                                                            ->orderBy('nombre')
                                                            ->pluck('nombre', 'id')
                                                            ->all())
                                                        ->searchable()
                                                        ->preload()
                                                        ->reactive()
                                                        ->required()
                                                        ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                                            $p = Producto::find($state);
                                                            $nombre = $p?->nombre;
                                                            $pts = (int) ($p?->puntos ?? 0);

                                                            if ($nombre === 'Producto Externo') {
                                                                $set('cantidad', 1);
                                                            }

                                                            $cantidad = (int) ($get('cantidad') ?? 1);
                                                            $set('puntos_linea', $cantidad * $pts);

                                                            $total = collect($get('../../productos') ?? [])
                                                                ->sum(fn($l) => (int) ($l['puntos_linea'] ?? 0));
                                                            $set('../../puntos', $total);
                                                        }),

                                                    TextInput::make('cantidad')
                                                        ->label('Cant.')
                                                        ->numeric()
                                                        ->minValue(1)
                                                        ->default(1)
                                                        ->required()
                                                        ->reactive()
                                                        ->readOnly(fn(Get $get) =>
                                                            Producto::find($get('producto_id'))?->nombre === 'Producto Externo')
                                                        ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                                            $p = Producto::find($get('producto_id'));
                                                            $pts = (int) ($p?->puntos ?? 0);
                                                            $cantidad = max((int) $state, 1);
                                                            $set('puntos_linea', $cantidad * $pts);
                                                            $total = collect($get('../../productos') ?? [])
                                                                ->sum(fn($l) => (int) ($l['puntos_linea'] ?? 0));
                                                            $set('../../puntos', $total);
                                                        }),

                                                    TextInput::make('puntos_linea')
                                                        ->label('Pts art.')
                                                        ->numeric()
                                                        ->disabled()
                                                        ->dehydrated(false),
                                                ]),
                                            ]),
                                    ]),
                            ]),
                    ])
                    ->action(function (Venta $record, array $data) {
                        DB::transaction(function () use ($record, $data) {
                            // 1️⃣ Asignar repartidor
                            $record->update(['repartidor_id' => $data['repartidor_id']]);

                            // 2️⃣ Crear ofertas del repartidor
                            $packs = $data['ofertas_repartidor'] ?? [];
                            foreach ($packs as $pack) {
                                $ofertaId = $pack['oferta_id'] ?? null;
                                if (!$ofertaId)
                                    continue;

                                $puntosPack = (int) collect($pack['productos'] ?? [])
                                    ->sum(fn($l) => (int) ($l['puntos_linea'] ?? 0));

                                $vo = $record->ventaOfertas()->create([
                                    'oferta_id' => $ofertaId,
                                    'puntos' => $puntosPack,
                                ]);

                                foreach (($pack['productos'] ?? []) as $l) {
                                    $productoId = $l['producto_id'] ?? null;
                                    $cantidad = (int) ($l['cantidad'] ?? 0);
                                    if (!$productoId || $cantidad <= 0)
                                        continue;

                                    $vo->productos()->create([
                                        'producto_id' => $productoId,
                                        'cantidad' => $cantidad,
                                        'cantidad_entregada' => $cantidad,
                                        'puntos_linea' => (int) ($l['puntos_linea'] ?? 0),
                                        'vendido_por' => VendidoPor::Repartidor->value,
                                    ]);
                                }
                            }

                            // 3️⃣ Recalcular importe total
                            $nuevoTotal = $record->ventaOfertas()
                                ->with('oferta:id,precio_base')
                                ->get()
                                ->sum(fn($vo) => (float) ($vo->oferta->precio_base ?? 0));

                            $record->update(['importe_total' => number_format($nuevoTotal, 2, '.', '')]);
                            if (method_exists($record, 'recomputarImportesDesdeOfertas')) {
                                $record->recomputarImportesDesdeOfertas();
                            }

                            // 4️⃣ Crear / actualizar reparto
                            $reparto = Reparto::firstOrNew(['venta_id' => $record->id]);
                            $reparto->estado_entrega = EstadoEntrega::COMPLETO;
                            $reparto->estado = EstadoReparto::ENTREGA_VENTA;
                            $reparto->cliente_firma_garantias = $reparto->cliente_firma_garantias ?? false;
                            $reparto->cliente_comentario_goodwork = $reparto->cliente_comentario_goodwork ?? false;
                            $reparto->cliente_firma_digital = $reparto->cliente_firma_digital ?? false;
                            $reparto->save();
                        });

                        Notification::make()
                            ->title('Entrega con venta registrada')
                            ->body('Se añadieron las ofertas del repartidor y se marcó la entrega como completa.')
                            ->success()
                            ->send();
                    })
                    ->modalHeading('Registrar entrega con venta')
                    ->modalSubmitActionLabel('Guardar entrega'),
            ])
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
            'index' => Pages\ListVentasViejas::route('/'),
            'create' => Pages\CreateVentasViejas::route('/create'),
            'edit' => Pages\EditVentasViejas::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
    public static function canEdit($record): bool
    {
        return false;
    }
    public static function canDelete($record): bool
    {
        return false;
    }
    public static function canForceDelete($record): bool
    {
        return false;
    }
    public static function canViewAny(): bool
    {
        return true;
    }
}
