<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\Admin\Resources\VentaResource as AdminVentaResource;
use App\Filament\Admin\Resources\VentaResource\RelationManagers\AsociadasRelationManager;
use App\Filament\SuperAdmin\Resources\VentaResource\RelationManagers\PdfBorradosRelationManager;
use App\Filament\SuperAdmin\Resources\VentaResource\RelationManagers\PdfDescargasRelationManager;
use App\Enums\EstadoVenta;
use App\Filament\Support\SuperAdminVentaCustomerId;
use App\Filament\SuperAdmin\Resources\VentaResource\Pages;
use App\Models\ContratoRecuperado;
use App\Models\ContratoRecoveryItem;
use App\Models\Venta;
use App\Support\Filament\VentaDatosInfolist;
use App\Support\Filament\VentaSoftDeleteTableAction;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VentaResource extends Resource
{
    protected static ?string $model = Venta::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel = 'Contratos';
    protected static ?string $modelLabel = 'Contrato';
    protected static ?string $pluralModelLabel = 'Contratos';
    protected static ?string $breadcrumb = 'Contratos';
    protected static ?string $slug = 'ventas-admin';
    protected static ?int $navigationSort = -8;

    public static function form(Form $form): Form
    {
        $form = AdminVentaResource::form($form);

        static::applyDniMask($form->getComponents());

        return $form;
    }

    /**
     * Solo en SuperAdmin: separa el DNI en grupos de 4 caracteres para facilitar su lectura.
     */
    protected static function applyDniMask(array $components): void
    {
        foreach ($components as $component) {
            if (! $component instanceof Component) {
                continue;
            }

            if ($component instanceof TextInput && $component->getName() === 'dni') {
                $component->mask('**** **** ****');

                continue;
            }

            if (method_exists($component, 'getChildComponents')) {
                static::applyDniMask($component->getChildComponents());
            }
        }
    }

    public static function table(Table $table): Table
    {
        $table = AdminVentaResource::table($table);

        $columns = $table->getColumns();

        $columnsWithoutIndividualSearch = [
            'nro_contr_adm',
            'note.nro_nota',
            'nro_cliente_adm',
        ];

        // Columnas exclusivas del recurso Contratos de Admin (no deben verse en SuperAdmin).
        $columnsOnlyInAdmin = [
            'ver_pdf',
        ];

        foreach ($columns as $key => $column) {
            if (in_array($column->getName(), $columnsOnlyInAdmin, true)) {
                unset($columns[$key]);
                continue;
            }

            if ($column->getName() === 'customer.name') {
                $column->searchable(['first_names', 'last_names']);
            }

            if (in_array($column->getName(), $columnsWithoutIndividualSearch, true)) {
                $column->searchable();
            }

            // Nº recuperado → amarillo (warning); el resto verde (hereda Admin y se refuerza aquí).
            if ($column->getName() === 'nro_contr_adm') {
                $column
                    ->badge()
                    ->color(fn ($state): string => ContratoRecuperado::isRecuperado(
                        filled($state) ? (string) $state : null
                    ) ? 'warning' : 'success');
            }

            // Recuperados en «En revisión» (estado por defecto) → badge gris.
            if ($column->getName() === 'estado_venta') {
                $column->color(function ($state, Venta $record): string {
                    $estado = $state instanceof EstadoVenta
                        ? $state
                        : EstadoVenta::tryFrom((string) $state);

                    return ContratoRecuperado::estadoBadgeColor(
                        $estado,
                        $record->nro_contr_adm,
                    );
                });
            }
        }

        // Orden SuperAdmin: Nº Contrato, Nombre, Fecha…; ID-CL justo después de CF.
        $nombreColumn = null;
        $columnsWithoutNombre = [];

        foreach (array_values($columns) as $column) {
            if ($column->getName() === 'customer.name') {
                $nombreColumn = $column;
                continue;
            }

            $columnsWithoutNombre[] = $column;
        }

        $ordered = [];

        foreach ($columnsWithoutNombre as $column) {
            $ordered[] = $column;

            if ($column->getName() === 'nro_contr_adm' && $nombreColumn) {
                $ordered[] = $nombreColumn;
            }

            // Tras Nº Contrato + Nombre + Fecha: Ver Datos e Imagen (solo SuperAdmin).
            if ($column->getName() === 'fecha_venta') {
                $ordered[] = TextColumn::make('ver_datos')
                    ->label('Ver Datos')
                    ->state('VER DATOS')
                    ->badge()
                    ->color('info')
                    ->alignCenter()
                    ->grow(false)
                    ->action(
                        Tables\Actions\Action::make('verDatosVenta')
                            ->modalHeading(fn (Venta $record): string => 'Datos de la venta — '
                                .(filled($record->nro_contr_adm) ? (string) $record->nro_contr_adm : '#'.$record->id))
                            ->modalWidth(MaxWidth::FourExtraLarge)
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Cerrar')
                            ->infolist(fn (Venta $record): array => VentaDatosInfolist::schema(
                                $record,
                                static::getUrl('edit', ['record' => $record]),
                            ))
                    );

                $ordered[] = TextColumn::make('ver_imagen')
                    ->label('Imagen')
                    ->state(function (Venta $record): string {
                        return static::contratoImagenUrl($record) ? 'Ver Imagen' : '—';
                    })
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Ver Imagen' ? 'warning' : 'gray')
                    ->url(fn (Venta $record): ?string => static::contratoImagenUrl($record))
                    ->openUrlInNewTab()
                    ->tooltip('Foto original usada para recuperar este contrato')
                    ->alignCenter()
                    ->grow(false);
            }

            if ($column->getName() === 'cf') {
                $ordered[] = SuperAdminVentaCustomerId::tableColumn();
            }
        }

        $allColumns = array_values(array_filter($ordered));

        foreach ($allColumns as $column) {
            // Preserva el "oculto por defecto" ya definido en la columna (p.ej. Teléfonos_CL,
            // Tlf_Com, CP), en vez de resetearlo a false al llamar toggleable() sin argumentos.
            $column->toggleable(isToggledHiddenByDefault: $column->isToggledHiddenByDefault());
        }

        return $table
            ->defaultSort('id', 'desc')
            ->columns($allColumns)
            ->filtersLayout(Tables\Enums\FiltersLayout::Dropdown)
            ->actions([
                Tables\Actions\EditAction::make(),
                VentaSoftDeleteTableAction::make(),
            ])
            ->persistSearchInSession()
            ->persistColumnSearchesInSession()
            ->persistFiltersInSession();
    }

    public static function getRelations(): array
    {
        return [
            AsociadasRelationManager::class,
            PdfDescargasRelationManager::class,
            PdfBorradosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVentas::route('/'),
            'edit' => Pages\EditVenta::route('/{record}/edit'),
            'create-b' => Pages\CreateContratoBPage::route('/{record}/create-b'),
        ];
    }

    /** @var array<int, string|null> */
    protected static array $contratoImagenUrlCache = [];

    /**
     * Foto usada para recuperar: prioriza el ítem de recovery (misma ruta que
     * Recuperar contrato); si no hay, cae a contrato_firmado.
     */
    public static function contratoImagenUrl(Venta $record): ?string
    {
        $id = (int) $record->id;
        if (array_key_exists($id, static::$contratoImagenUrlCache)) {
            return static::$contratoImagenUrlCache[$id];
        }

        $nro = filled($record->nro_contr_adm) ? (string) $record->nro_contr_adm : null;

        $item = ContratoRecoveryItem::query()
            ->whereNotNull('documents')
            ->where(function ($q) use ($record, $nro): void {
                $q->where('venta_id', $record->id);
                if ($nro !== null) {
                    $q->orWhere('nro_contr_adm', $nro);
                }
            })
            ->orderByRaw('CASE WHEN venta_id = ? THEN 0 ELSE 1 END', [$record->id])
            ->first();

        if ($item) {
            $docs = collect($item->documents ?? [])
                ->filter(fn ($d) => is_array($d) && filled($d['path'] ?? null))
                ->values();

            if ($docs->isNotEmpty()) {
                return static::$contratoImagenUrlCache[$id] = route('recovery-items.image', [
                    'item' => $item,
                    'index' => 0,
                ]);
            }
        }

        if (filled($record->contrato_firmado)) {
            return static::$contratoImagenUrlCache[$id] = $record->contrato_firmado_url;
        }

        return static::$contratoImagenUrlCache[$id] = null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function canForceDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }
}
