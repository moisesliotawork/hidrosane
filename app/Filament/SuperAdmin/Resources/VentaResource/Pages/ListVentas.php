<?php

namespace App\Filament\SuperAdmin\Resources\VentaResource\Pages;

use App\Filament\Admin\Resources\VentaResource\Pages\ListVentas as BaseListVentas;
use App\Filament\SuperAdmin\Resources\VentaResource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListVentas extends BaseListVentas
{
    protected static string $resource = VentaResource::class;

    /** Búsqueda dedicada por nº de contrato (toolbar izquierda, solo SuperAdmin). */
    public ?string $nroContratoBusqueda = '';

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
}
