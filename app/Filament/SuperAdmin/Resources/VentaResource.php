<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\Admin\Resources\VentaResource as AdminVentaResource;
use App\Filament\Admin\Resources\VentaResource\RelationManagers\AsociadasRelationManager;
use App\Filament\SuperAdmin\Resources\VentaResource\RelationManagers\PdfBorradosRelationManager;
use App\Filament\SuperAdmin\Resources\VentaResource\RelationManagers\PdfDescargasRelationManager;
use App\Filament\Support\SuperAdminVentaCustomerId;
use App\Filament\SuperAdmin\Resources\VentaResource\Pages;
use App\Models\Venta;
use App\Support\Filament\VentaSoftDeleteTableAction;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
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
        $nameColumn = null;

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
                $nameColumn = $column;
                unset($columns[$key]);
                continue;
            }

            if (in_array($column->getName(), $columnsWithoutIndividualSearch, true)) {
                $column->searchable();
            }
        }

        $allColumns = array_values(array_filter([
            SuperAdminVentaCustomerId::tableColumn(),
            $nameColumn,
            ...array_values($columns),
        ]));

        foreach ($allColumns as $column) {
            // Preserva el "oculto por defecto" ya definido en la columna (p.ej. Teléfonos_CL,
            // Tlf_Com, CP), en vez de resetearlo a false al llamar toggleable() sin argumentos.
            $column->toggleable(isToggledHiddenByDefault: $column->isToggledHiddenByDefault());
        }

        return $table
            ->columns($allColumns)
            ->filtersLayout(Tables\Enums\FiltersLayout::Dropdown)
            ->actions([
                Tables\Actions\EditAction::make(),
                VentaSoftDeleteTableAction::make(),
            ])
            ->persistSearchInSession()
            ->persistColumnSearchesInSession()
            ->persistFiltersInSession()
            ->persistSortInSession();
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

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return true;
    }
}
