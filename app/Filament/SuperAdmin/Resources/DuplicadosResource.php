<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\DuplicadosResource\Pages;
use App\Models\Customer;
use App\Services\CustomerDuplicateSearchService;
use App\Services\CustomerMergeService;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

class DuplicadosResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';
    protected static ?string $navigationLabel = 'Duplicados';
    protected static ?string $modelLabel = 'Duplicado';
    protected static ?string $pluralModelLabel = 'Duplicados';
    protected static ?string $slug = 'duplicados';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'latestNote.user:id,name,last_name,empleado_id',
            ])
            ->withCount('ventas')
            ->orderByRaw(CustomerDuplicateSearchService::orderByLatestActivitySql());
    }

    public static function table(Table $table): Table
    {
        $fmt = fn(?string $p): string => $p
            ? implode(' ', str_split(preg_replace('/\D+/', '', $p), 3))
            : '—';

        return $table
            ->columns([
                TextColumn::make('nombre_cliente')
                    ->label('Nombre del Cliente')
                    ->getStateUsing(fn(Customer $r) => mb_strtoupper(trim($r->first_names . ' ' . $r->last_names)))
                    ->weight(FontWeight::Bold)
                    ->color(Color::Green)
                    ->searchable(['first_names', 'last_names'])
                    ->wrap(),

                TextColumn::make('dni')
                    ->label('DNI')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nro_nota')
                    ->label('Nro. Nota')
                    ->badge()
                    ->color(Color::Indigo)
                    ->getStateUsing(fn(Customer $r) => $r->latestNote?->nro_nota ?? '—'),

                TextColumn::make('id')
                    ->label('ID Cliente')
                    ->badge()
                    ->color(Color::Gray)
                    ->sortable(),

                TextColumn::make('phone')
                    ->label('Teléfono 1')
                    ->getStateUsing(fn(Customer $r) => $fmt($r->phone))
                    ->color(Color::Amber)
                    ->weight(FontWeight::Bold)
                    ->searchable(),

                TextColumn::make('secondary_phone')
                    ->label('Teléfono 2')
                    ->getStateUsing(fn(Customer $r) => $fmt($r->secondary_phone))
                    ->color(Color::Amber)
                    ->searchable(),

                TextColumn::make('third_phone')
                    ->label('Teléfono 3')
                    ->getStateUsing(fn(Customer $r) => $fmt($r->third_phone))
                    ->color(Color::Amber)
                    ->searchable(),

                TextColumn::make('phone1_commercial')
                    ->label('Tel. Comercial 1')
                    ->getStateUsing(fn(Customer $r) => $fmt($r->phone1_commercial))
                    ->color(Color::Teal)
                    ->searchable(),

                TextColumn::make('phone2_commercial')
                    ->label('Tel. Comercial 2')
                    ->getStateUsing(fn(Customer $r) => $fmt($r->phone2_commercial))
                    ->color(Color::Teal)
                    ->searchable(),

                TextColumn::make('fecha_asignacion')
                    ->label('Fecha Asignación')
                    ->getStateUsing(function (Customer $r): string {
                        $date = $r->latestNote?->assignment_date;
                        if (!$date) return '—';
                        return \Carbon\Carbon::parse($date)->format('d/m/Y H:i');
                    })
                    ->sortable(query: function (Builder $query, string $direction) {
                        $query->orderBy(
                            \App\Models\Note::select('assignment_date')
                                ->whereColumn('notes.customer_id', 'customers.id')
                                ->orderBy('id', 'desc')
                                ->limit(1),
                            $direction
                        );
                    }),

                TextColumn::make('teleoperadora')
                    ->label('Teleoperadora')
                    ->getStateUsing(function (Customer $r): string {
                        $user = $r->latestNote?->user;
                        if (!$user) return '—';
                        return trim($user->name . ' ' . $user->last_name);
                    })
                    ->searchable(query: function (Builder $query, string $search) {
                        $query->whereHas('latestNote.user', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                              ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('fuente')
                    ->label('Fuente')
                    ->badge()
                    ->getStateUsing(function (Customer $r): string {
                        $fuente = $r->latestNote?->fuente;
                        if (!$fuente) return '—';
                        if ($fuente instanceof \App\Enums\FuenteNotas) {
                            return $fuente->getLabel();
                        }
                        $enum = \App\Enums\FuenteNotas::tryFrom((string) $fuente);
                        return $enum ? $enum->getLabel() : (string) $fuente;
                    })
                    ->color(function (Customer $r): string {
                        $fuente = $r->latestNote?->fuente;
                        if (!$fuente) return 'gray';
                        if (!($fuente instanceof \App\Enums\FuenteNotas)) {
                            $fuente = \App\Enums\FuenteNotas::tryFrom((string) $fuente);
                        }
                        return $fuente?->getColor() ?? 'gray';
                    }),

                TextColumn::make('ventas_count')
                    ->label('Nro. Ventas')
                    ->badge()
                    ->color(fn(int $state): string => $state > 0 ? 'success' : 'gray')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([25, 50, 100, 'all'])
            ->filters([])
            ->emptyStateHeading('Buscar duplicados')
            ->emptyStateDescription('Pulsa «Buscar duplicados» para escanear clientes con el mismo DNI y nombre parcial o total igual (el teléfono puede coincidir o no).')
            ->emptyStateIcon('heroicon-o-magnifying-glass')
            ->actions([])
            ->bulkActions([
                Tables\Actions\BulkAction::make('fusionar')
                    ->label('Fusionar seleccionados')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Fusionar clientes duplicados')
                    ->modalDescription(function (Collection $records): HtmlString {
                        if ($records->count() !== 2) {
                            return new HtmlString(
                                '<p class="text-danger-600 font-bold">Debes seleccionar exactamente 2 clientes para fusionar.</p>'
                            );
                        }

                        $sorted  = $records->sortBy([
                            [fn(Customer $c) => optional($c->created_at)->timestamp ?? PHP_INT_MAX, 'asc'],
                            [fn(Customer $c) => $c->id, 'asc'],
                        ])->values();

                        /** @var Customer $keeper */
                        $keeper   = $sorted->first();
                        /** @var Customer $toDelete */
                        $toDelete = $sorted->last();

                        $keeperNotes  = $keeper->notes()->count();
                        $keeperVentas = $keeper->ventas()->count();
                        $deleteNotes  = $toDelete->notes()->count();
                        $deleteVentas = $toDelete->ventas()->count();

                        $keeperName  = mb_strtoupper(trim($keeper->first_names . ' ' . $keeper->last_names));
                        $deleteName  = mb_strtoupper(trim($toDelete->first_names . ' ' . $toDelete->last_names));

                        return new HtmlString(
                            '<div style="font-size:14px;line-height:1.6;">'
                            . '<p style="margin-bottom:12px;">Se fusionarán los 2 clientes en <strong>1 solo registro</strong>. '
                            . 'El cliente más antiguo será conservado y el más reciente será <strong>eliminado definitivamente</strong>.</p>'
                            . '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">'

                            // Keeper
                            . '<div style="background:#166534;border-radius:8px;padding:12px;">'
                            . '<div style="color:#bbf7d0;font-size:11px;font-weight:700;text-transform:uppercase;margin-bottom:6px;">✅ SE CONSERVA (más antiguo)</div>'
                            . '<div style="color:#fff;font-weight:700;">' . e($keeperName) . '</div>'
                            . '<div style="color:#d1fae5;">ID: ' . $keeper->id . ' &nbsp;|&nbsp; DNI: ' . e($keeper->dni ?? '—') . '</div>'
                            . '<div style="color:#d1fae5;">Notas: ' . $keeperNotes . ' &nbsp;|&nbsp; Ventas: ' . $keeperVentas . '</div>'
                            . '<div style="color:#d1fae5;">Creado: ' . optional($keeper->created_at)->format('d/m/Y H:i') . '</div>'
                            . '</div>'

                            // ToDelete
                            . '<div style="background:#7f1d1d;border-radius:8px;padding:12px;">'
                            . '<div style="color:#fca5a5;font-size:11px;font-weight:700;text-transform:uppercase;margin-bottom:6px;">🗑️ SE ELIMINA (más reciente)</div>'
                            . '<div style="color:#fff;font-weight:700;">' . e($deleteName) . '</div>'
                            . '<div style="color:#fecaca;">ID: ' . $toDelete->id . ' &nbsp;|&nbsp; DNI: ' . e($toDelete->dni ?? '—') . '</div>'
                            . '<div style="color:#fecaca;">Notas: ' . $deleteNotes . ' &nbsp;|&nbsp; Ventas: ' . $deleteVentas . '</div>'
                            . '<div style="color:#fecaca;">Creado: ' . optional($toDelete->created_at)->format('d/m/Y H:i') . '</div>'
                            . '</div>'

                            . '</div>'
                            . '<p style="color:#fbbf24;font-weight:600;">⚠️ Todas las notas, ventas y datos del cliente eliminado pasarán al conservado. Esta acción no se puede deshacer.</p>'
                            . '</div>'
                        );
                    })
                    ->modalSubmitActionLabel('Sí, fusionar y eliminar')
                    ->action(function (Collection $records, CustomerMergeService $mergeService) {
                        if ($records->count() !== 2) {
                            Notification::make()
                                ->title('Selecciona exactamente 2 clientes')
                                ->danger()
                                ->send();
                            return;
                        }

                        $sorted = $records->sortBy([
                            [fn(Customer $c) => optional($c->created_at)->timestamp ?? PHP_INT_MAX, 'asc'],
                            [fn(Customer $c) => $c->id, 'asc'],
                        ])->values();

                        $keeper   = $sorted->first();
                        $toDelete = $sorted->last();

                        try {
                            $result = $mergeService->mergeByIds($keeper->id, $toDelete->id, auth()->id());

                            Notification::make()
                                ->title('Fusión completada')
                                ->body(
                                    "Cliente conservado: #{$result['keeper_id']} | "
                                    . "Notas movidas: {$result['notes_updated']} | "
                                    . "Ventas movidas: {$result['ventas_updated']}"
                                )
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Error al fusionar')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDuplicados::route('/'),
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

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
