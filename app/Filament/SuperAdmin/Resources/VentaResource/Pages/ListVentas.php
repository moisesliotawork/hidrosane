<?php

namespace App\Filament\SuperAdmin\Resources\VentaResource\Pages;

use App\Filament\Admin\Resources\VentaResource\Pages\ListVentas as BaseListVentas;
use App\Filament\SuperAdmin\Resources\VentaResource;
use App\Models\ContratoRecuperado;
use App\Models\ContratoRecoveryItem;
use Filament\Resources\Components\Tab;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

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

    public function getDefaultActiveTab(): string|int|null
    {
        return 'todos';
    }

    public function getTabs(): array
    {
        $page = $this;

        return [
            'todos' => Tab::make('TODOS')
                ->badge(fn (): int => static::getResource()::getEloquentQuery()->count())
                ->badgeColor('success')
                ->extraAttributes(function () use ($page): array {
                    $active = strval($page->activeTab) === 'todos';

                    return [
                        'class' => $active
                            ? '!bg-success-500/20 !text-success-700 ring-2 ring-inset ring-success-500 dark:!bg-success-400/20 dark:!text-success-300'
                            : '!text-success-600 dark:!text-success-400',
                    ];
                }),

            'recuperados' => Tab::make('RECUPERADOS')
                ->badge(fn (): int => static::getResource()::getEloquentQuery()
                    ->tap(fn (Builder $q) => static::constrainToRecuperados($q))
                    ->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => static::constrainToRecuperados($query))
                ->extraAttributes(function () use ($page): array {
                    $active = strval($page->activeTab) === 'recuperados';

                    return [
                        'class' => $active
                            ? '!bg-warning-500/20 !text-warning-700 ring-2 ring-inset ring-warning-500 dark:!bg-warning-400/20 dark:!text-warning-300'
                            : '!text-warning-600 dark:!text-warning-400',
                    ];
                }),
        ];
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

    /**
     * Ventas cuyo nº está en contratos_recuperados o en recovery items ya agregados.
     */
    protected static function constrainToRecuperados(Builder $query): Builder
    {
        return $query->where(function (Builder $outer): void {
            if (Schema::hasTable('contratos_recuperados')) {
                $outer->whereIn('nro_contr_adm', ContratoRecuperado::query()->select('nro_contr_adm'));
            }

            if (Schema::hasTable('contrato_recovery_items')) {
                $outer->orWhereIn(
                    'nro_contr_adm',
                    ContratoRecoveryItem::query()
                        ->where('status', ContratoRecoveryItem::STATUS_ADDED)
                        ->whereNotNull('nro_contr_adm')
                        ->select('nro_contr_adm')
                );
            }
        });
    }
}
