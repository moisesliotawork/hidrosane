<?php

namespace App\Filament\Repartidor\Resources;

use App\Filament\Repartidor\Resources\HistoricoRepartosResource\Pages;
use App\Models\Reparto;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\EstadoEntrega;
use App\Enums\EstadoReparto;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Textarea;
use App\Models\DeclararModificacionEntrega;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
;
use App\Filament\Repartidor\Resources\EntregaSimpleResource;

class HistoricoRepartosResource extends Resource
{
    protected static ?string $model = Reparto::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationLabel = 'Histórico de Repartos';
    protected static ?string $pluralModelLabel = 'Histórico de Repartos';
    protected static ?string $slug = 'historico-repartos';

    /** Solo lectura */
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

    public static function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $panelId = filament()->getCurrentPanel()?->getId();

                return Reparto::query()
                    ->leftJoin('ventas', 'ventas.id', '=', 'repartos.venta_id')
                    ->leftJoin('customers', 'customers.id', '=', 'ventas.customer_id')
                    ->with(['venta.comercial'])
                    ->when(
                        $panelId === 'repartidor',
                        fn(Builder $q) => $q->where('ventas.repartidor_id', auth()->id()),
                        fn(Builder $q) => $q->whereNotNull('ventas.id')
                    )

                    // ✅ SOLO repartos asignados para HOY (por fecha_entrega)
                    ->whereDate('ventas.fecha_entrega', Carbon::today())

                    ->select('repartos.*');
            })

            ->columns([
                    // ID / Nº de Nota
                    TextColumn::make('venta.note.nro_nota')
                        ->label('# Nota')
                        ->badge()
                        ->color('info')
                        ->formatStateUsing(fn($state) => $state ? "{$state}" : '-')
                        ->searchable(),

                    // Nombre completo del cliente
                    TextColumn::make('cliente')
                        ->label('Cliente')
                        ->state(function ($record) {
                            $c = $record->venta?->customer;
                            return $c?->full_name ?? trim(($c->first_names ?? '') . ' ' . ($c->last_names ?? ''));
                        })
                        ->searchable(query: function (Builder $query, string $search): Builder {
                            $like = "%{$search}%";
                            return $query->where(function ($qq) use ($like) {
                                $qq->where('customers.first_names', 'like', $like)
                                    ->orWhere('customers.last_names', 'like', $like)
                                    ->orWhereRaw(
                                        "CONCAT(COALESCE(customers.first_names,''),' ',COALESCE(customers.last_names,'')) LIKE ?",
                                        [$like]
                                    )
                                    ->orWhere('customers.dni', 'like', $like);
                            });
                        })
                        ->sortable(query: function (Builder $query, string $direction): Builder {
                            return $query->orderByRaw(
                                "TRIM(CONCAT(COALESCE(customers.first_names,''),' ',COALESCE(customers.last_names,''))) " .
                                ($direction === 'desc' ? 'DESC' : 'ASC')
                            );
                        }),


                    TextColumn::make('venta.customer.primary_address')
                        ->label('Dirección')
                        ->formatStateUsing(function ($state) {
                            if (!$state)
                                return '-';
                            return wordwrap($state, 40, "\n", true);
                        })
                        ->extraAttributes(['style' => 'white-space: pre-line;']) // respeta \n
                        ->wrap()

                        ->searchable(query: function (Builder $query, string $search): Builder {
                            return $query->where('customers.primary_address', 'like', "%{$search}%");
                        })

                        ->sortable(query: function (Builder $query, string $direction): Builder {
                            return $query->orderBy('customers.primary_address', $direction);
                        }),

                    // Teléfono 1
                    TextColumn::make('telefono_1')
                        ->label('Teléfono 1')
                        ->state(function ($record) {
                            $tel = $record->venta?->customer?->phone;
                            return blank($tel) ? '-' : $tel;
                        })
                        // Búsqueda robusta por dígitos (ignora espacios, guiones, paréntesis, puntos)
                        ->searchable(query: function (Builder $query, string $search): Builder {
                            $digits = preg_replace('/\D+/', '', $search ?? '');
                            // Si el usuario no introdujo dígitos, usa LIKE normal
                            if ($digits === '') {
                                return $query->where('customers.phone', 'like', "%{$search}%");
                            }
                            $norm = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(customers.phone, ' ', ''), '-', ''), '.', ''), '(', ''), ')', '')";
                            return $query->whereRaw("{$norm} LIKE ?", ["%{$digits}%"]);
                        })
                        // Orden por el valor directo en BD
                        ->sortable(query: function (Builder $query, string $direction): Builder {
                            return $query->orderBy('customers.phone', $direction);
                        }),

                    // Teléfono 2
                    TextColumn::make('telefono_2')
                        ->label('Teléfono 2')
                        ->state(function ($record) {
                            $tel = $record->venta?->customer?->secondary_phone;
                            return blank($tel) ? '-' : $tel;
                        })
                        ->searchable(query: function (Builder $query, string $search): Builder {
                            $digits = preg_replace('/\D+/', '', $search ?? '');
                            if ($digits === '') {
                                return $query->where('customers.secondary_phone', 'like', "%{$search}%");
                            }
                            $norm = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(customers.secondary_phone, ' ', ''), '-', ''), '.', ''), '(', ''), ')', '')";
                            return $query->whereRaw("{$norm} LIKE ?", ["%{$digits}%"]);
                        })
                        ->sortable(query: function (Builder $query, string $direction): Builder {
                            return $query->orderBy('customers.secondary_phone', $direction);
                        }),


                    // Fecha declaración de la venta
                    TextColumn::make('venta.fecha_venta')
                        ->label('Fecha Venta')
                        ->sortable()
                        ->dateTime('d/m/Y'),

                    // FECHA DE ENTREGA
                    TextColumn::make('venta.fecha_entrega')
                        ->label('Fecha Entr.')
                        ->date('d/m/Y') // si tu columna es DATE/DATETIME; si es string, dímelo y lo adapto
                        ->sortable(query: function (Builder $query, string $direction): Builder {
                            return $query->orderBy('ventas.fecha_entrega', $direction);
                        }),

                    // HORARIO DE ENTREGA
                    TextColumn::make('venta.horario_entrega')
                        ->label('Horario Entr.')
                        ->state(function ($record) {
                            $h = $record->venta?->horario_entrega;
                            return blank($h) ? '-' : $h;
                        }),


                    // Estado del reparto (campo "estado" en Reparto)
                    TextColumn::make('estado')
                        ->label('Estado reparto')
                        ->toggleable()
                        ->badge()
                        ->formatStateUsing(fn(?EstadoReparto $state) => $state?->label())
                        ->color(fn(?EstadoReparto $state) => $state?->color()),

                    // Estado de la entrega (enum EstadoEntrega casteado en Reparto)
                    TextColumn::make('estado_entrega')
                        ->label('Estado de la entrega')
                        ->toggleable()
                        ->badge()
                        ->formatStateUsing(function ($state) {
                            // Si tu enum tiene ->label(), úsalo; si no, mostramos el valor de forma legible
                            if ($state instanceof EstadoEntrega && method_exists($state, 'label')) {
                                return $state->label();
                            }
                            $value = $state instanceof EstadoEntrega ? $state->value : (string) $state;
                            return ucfirst(strtolower(str_replace('_', ' ', $value)));
                        }),

                    // Estado de la venta (enum en Venta con label/color)
                    TextColumn::make('venta.estado_venta')
                        ->label('Estado venta')
                        ->badge()
                        ->toggleable()
                        ->formatStateUsing(fn($record) => $record->venta?->estado_venta?->label() ?? '')
                        ->color(fn($record) => $record->venta?->estado_venta?->color()),

                ])
            ->defaultSort('id', 'desc')
            ->actions([
                    // ─────────────────────────────────────────────────────────────
                    //  A) Gestionar parcial → modal + redirección a página en blanco
                    // ─────────────────────────────────────────────────────────────
                    Action::make('gestionarParcial')
                        ->label('Modificar Entrega')
                        ->icon('heroicon-o-square-3-stack-3d')
                        ->visible(fn(Reparto $record) => in_array(
                            $record->estado_entrega,
                            [EstadoEntrega::PARCIAL, EstadoEntrega::NO_ENTREGADO]
                        ))
                        ->modalHeading('Entrega parcial')
                        ->modalSubmitActionLabel('Siguiente')
                        ->form([
                                Textarea::make('observacion')
                                    ->label('Observación / qué quieres hacer')
                                    ->rows(4)
                                    ->required()
                                    ->maxLength(1000),
                            ])
                        ->action(function (Reparto $record, array $data) {
                            // Aseguramos que exista venta
                            $venta = $record->venta;
                            abort_unless($venta, 404, 'La entrega no tiene venta asociada.');

                            // Guardar declaración
                            $decl = DeclararModificacionEntrega::create([
                                'venta_id' => $venta->id,
                                'user_id' => Auth::id(),
                                'fecha' => Carbon::now(),
                                'observacion' => $data['observacion'],
                            ]);

                            // Pasamos el ID a la page (por sesión o querystring). Aquí: sesión
                            session()->flash('decl_mod_entrega_id', $decl->id);

                            // Redirigir a la page en blanco
                            return redirect()->to(
                                route('filament.repartidor.resources.historico-repartos.parcial', ['record' => $record])
                            );
                        }),

                    // ─────────────────────────────────────────────────────────────
                    //  B) +DOCS → ir a editar la venta para subir documentos
                    // ─────────────────────────────────────────────────────────────
                    Action::make('+DOCS')
                        ->label('+DOCS')
                        ->icon('heroicon-o-document-plus')
                        ->url(
                            fn(Reparto $record) =>
                            HistoricoRepartosResource::getUrl('docs', [
                                'record' => $record,           // <- id del reparto
                            ], panel: 'repartidor')
                        )
                        ->openUrlInNewTab(false)
                        ->disabled(fn($record) => blank($record->venta)),

                    Action::make('llevame')
                        ->label('Llévame')
                        ->icon('heroicon-o-map-pin')
                        ->color('success')
                        ->modalHeading('¿Qué destino quieres usar?')
                        ->modalDescription('Elige la fuente para navegar en Google Maps.')
                        // Oculta el submit por defecto y usa solo los botones personalizados
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Cerrar')
                        // El action principal solo se muestra si existe al menos 1 opción
                        ->visible(function (Reparto $record) {
                            $tieneDentro = filled($record->dentro_lat) && filled($record->dentro_lng);
                            $tieneGps = filled($record->lat) && filled($record->lng);
                            $c = $record->venta?->customer;
                            $tieneDir = filled($c?->primary_address);
                            return $tieneDentro || $tieneGps || $tieneDir;
                        })
                        ->modalActions([
                                // 1) DENTRO (dentro_lat/dentro_lng)
                                Action::make('usarDentro')
                                    ->label('Usar DENTRO')
                                    ->icon('heroicon-o-cursor-arrow-rays')
                                    ->color('success')
                                    ->visible(fn(Reparto $record) => filled($record->dentro_lat) && filled($record->dentro_lng))
                                    ->url(
                                        fn(Reparto $record) =>
                                        "https://www.google.com/maps/dir/?api=1&destination={$record->dentro_lat},{$record->dentro_lng}&travelmode=driving",
                                        shouldOpenInNewTab: true
                                    ),

                                // 2) GPS (lat/lng del reparto)
                                Action::make('usarGps')
                                    ->label('Usar GPS (lat/lng)')
                                    ->icon('heroicon-o-map')
                                    ->color('warning')
                                    ->visible(fn(Reparto $record) => filled($record->lat) && filled($record->lng))
                                    ->url(
                                        fn(Reparto $record) =>
                                        "https://www.google.com/maps/dir/?api=1&destination={$record->lat},{$record->lng}&travelmode=driving",
                                        shouldOpenInNewTab: true
                                    ),

                                // 3) DIRECCIÓN (primary_address + CP + Ciudad + España)
                                Action::make('usarDireccion')
                                    ->label('Usar Dirección')
                                    ->icon('heroicon-o-building-storefront')
                                    ->color('info')
                                    ->visible(function (Reparto $record) {
                                        return filled($record->venta?->customer?->primary_address);
                                    })
                                    ->url(function (Reparto $record) {
                                        $c = $record->venta?->customer;
                                        $addr = trim((string) ($c?->primary_address ?? ''));
                                        $cp = $c?->postal_code ?? null;
                                        $city = $c?->ciudad ?? null; // City.title
                                        $parts = array_filter([$addr, $city, 'España']);
                                        if (empty($parts))
                                            return null;

                                        $dest = rawurlencode(implode(', ', $parts));
                                        return "https://www.google.com/maps/dir/?api=1&destination={$dest}&travelmode=driving";
                                    }, shouldOpenInNewTab: true),
                            ]),


                ])
            ->bulkActions([]); // sin bulk
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHistoricoRepartos::route('/'),
            'parcial' => Pages\GestionParcial::route('/{record}/parcial'),
            'docs' => Pages\GestionDocumentos::route('/{record}/docs'),
        ];
    }
}
