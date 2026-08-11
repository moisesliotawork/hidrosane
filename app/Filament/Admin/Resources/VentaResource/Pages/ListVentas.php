<?php

namespace App\Filament\Admin\Resources\VentaResource\Pages;

use App\Filament\Admin\Resources\VentaResource;
use Closure;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ListVentas extends ListRecords
{
    protected static string $resource = VentaResource::class;

    /** Búsqueda dedicada por nº de contrato (toolbar izquierda). */
    public ?string $nroContratoBusqueda = '';

    /**
     * Nota sobre visibilidad "oculta por defecto" (p.ej. Teléfonos_CL, Tlf_Com, CP):
     * se define directamente en cada columna con ->toggleable(isToggledHiddenByDefault: true)
     * dentro del resource. NO se debe forzar aquí en mount(), porque mutar
     * $this->toggledTableColumns antes de que el trait InteractsWithTable rellene su
     * estado por defecto/sesión hace que ese relleno se salte por completo (al ver el
     * array ya no vacío), dejando todas las demás columnas "huérfanas" en el checkbox
     * de columnas (aparecen sin check aunque la columna sí se vea en la tabla).
     */

    /**
     * Versiona la clave de sesión con el set actual de columnas toggleable: si se
     * agregan/quitan columnas en el futuro, la sesión antigua queda obsoleta
     * automáticamente y los checkboxes vuelven a sincronizarse con la visibilidad
     * real, sin tener que purgar sesiones a mano.
     */
    public function getTableColumnToggleFormStateSessionKey(): string
    {
        $columnNames = collect($this->getTable()->getColumns())
            ->map(fn ($column) => $column->getName())
            ->sort()
            ->implode('|');

        $hash = md5(static::class . '|' . $columnNames);

        return "tables.{$hash}_toggled_columns";
    }

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    public function updatedNroContratoBusqueda(): void
    {
        // El buscador global se persiste en sesión y, si queda un nombre viejo,
        // anula el filtro por nº contrato (AND). Al buscar por contrato, limpiarlo.
        if (filled(trim((string) ($this->nroContratoBusqueda ?? '')))) {
            $this->tableSearch = '';
            $this->tableColumnSearches = [];
        }

        $this->resetPage();
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->modifyQueryUsing(function (Builder $query): Builder {
                $term = trim((string) ($this->nroContratoBusqueda ?? ''));

                if ($term === '') {
                    return $query;
                }

                $compact = preg_replace('/\s+/', '', $term) ?: $term;

                return $query->where(function (Builder $inner) use ($term, $compact): void {
                    $inner->where('nro_contr_adm', 'like', "%{$term}%");

                    if ($compact !== $term) {
                        $inner->orWhere('nro_contr_adm', 'like', "%{$compact}%");
                    }
                });
            });
    }

    protected function getTableRecordUrlUsing(): ?Closure
    {
        return fn (Model $record): string => static::getResource()::getUrl('edit', ['record' => $record]);
    }

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
