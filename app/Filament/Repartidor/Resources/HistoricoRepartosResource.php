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
                    ->with([
                        'venta.note.customer.postalCode.city',
                        'venta.comercial',
                    ])
                    // Igual que tu Livewire: si es panel "repartidor", solo mis repartos
                    ->when($panelId === 'repartidor', function (Builder $q) {
                        $q->whereHas(
                            'venta',
                            fn($qq) =>
                            $qq->where('repartidor_id', auth()->id())
                        );
                    }, function (Builder $q) {
                        // En otros paneles: solo aseguramos que exista la venta
                        $q->whereHas('venta');
                    })
                    ->latest('id');
            })
            ->columns([
                // ID / Nº de Nota
                TextColumn::make('venta.note.nro_nota')
                    ->label('ID Nota')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state ? "Nota {$state}" : '-')
                    ->searchable(),

                // Fecha declaración de la venta
                TextColumn::make('venta.fecha_venta')
                    ->label('Fecha declaración')
                    ->dateTime('Y-m-d H:i'),

                // Nombre completo del cliente
                TextColumn::make('cliente')
                    ->label('Cliente')
                    ->state(function ($record) {
                        $c = $record->venta?->customer;
                        return $c?->full_name ?? trim(($c->first_names ?? '') . ' ' . ($c->last_names ?? ''));
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('venta.customer', function (Builder $q) use ($search) {
                            $like = "%{$search}%";
                            $q->where('first_names', 'like', $like)
                                ->orWhere('last_names', 'like', $like)
                                ->orWhere('dni', 'like', $like)
                                // si quieres buscar por el nombre completo “First Last”:
                                ->orWhereRaw("CONCAT(COALESCE(first_names,''),' ',COALESCE(last_names,'')) LIKE ?", [$like]);
                        });
                    }),

                // Estado del reparto (campo "estado" en Reparto)
                TextColumn::make('estado')
                    ->label('Estado reparto')
                    ->badge()
                    ->formatStateUsing(fn(?EstadoReparto $state) => $state?->label())
                    ->color(fn(?EstadoReparto $state) => $state?->color()),

                // Estado de la entrega (enum EstadoEntrega casteado en Reparto)
                TextColumn::make('estado_entrega')
                    ->label('Estado de la entrega')
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
                    ->visible(fn(Reparto $record) => $record->estado_entrega === EstadoEntrega::PARCIAL)
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
            ])      // sin acciones
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
