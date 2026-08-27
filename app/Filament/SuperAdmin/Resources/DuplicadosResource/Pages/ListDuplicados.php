<?php

namespace App\Filament\SuperAdmin\Resources\DuplicadosResource\Pages;

use App\Filament\SuperAdmin\Resources\DuplicadosResource;
use App\Filament\SuperAdmin\Resources\DuplicadosResource\Widgets\DuplicadosStatsWidget;
use App\Filament\SuperAdmin\Resources\DuplicadosResource\Widgets\FusionadosWidget;
use App\Services\CustomerAutoMergeService;
use App\Services\CustomerDuplicateSearchService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListDuplicados extends ListRecords
{
    protected static string $resource = DuplicadosResource::class;

    public bool $duplicatesSearched = false;

    public function mount(): void
    {
        $this->duplicatesSearched = CustomerDuplicateSearchService::duplicateIdsFromSession() !== [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ejecutarFusionAutomatica')
                ->label('Ejecutar fusión automática')
                ->icon('heroicon-o-bolt')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Ejecutar fusión automática')
                ->modalDescription('Fusiona clientes con el mismo nombre y teléfono compartido (misma lógica que el proceso diario a las 8:00).')
                ->modalSubmitActionLabel('Ejecutar ahora')
                ->action(function (CustomerAutoMergeService $autoMergeService): void {
                    $run = $autoMergeService->run(
                        mergedByUserId: auth()->id(),
                        trigger: 'manual',
                    );

                    $body = "{$run->merged_count} cliente(s) fusionado(s).";

                    if ($run->failed_count > 0) {
                        $body .= " {$run->failed_count} error(es).";
                    }

                    Notification::make()
                        ->title('Fusión automática completada')
                        ->body($body)
                        ->color($run->failed_count > 0 ? 'warning' : 'success')
                        ->send();
                }),

            Action::make('buscarDuplicados')
                ->label('Buscar duplicados')
                ->icon('heroicon-o-magnifying-glass')
                ->color('primary')
                ->action(function () {
                    $ids = CustomerDuplicateSearchService::refreshDuplicateIdsInSession();

                    $this->duplicatesSearched = true;

                    $count = count($ids);

                    if ($count === 0) {
                        Notification::make()
                            ->title('Sin duplicados')
                            ->body('No se encontraron clientes con el mismo DNI y nombre compatible, ni con el mismo nombre completo y teléfono compartido.')
                            ->warning()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Búsqueda completada')
                            ->body("Se encontraron {$count} clientes con posible duplicado.")
                            ->success()
                            ->send();
                    }

                    $this->resetTable();
                }),

            Action::make('fusionarTodos')
                ->label('Fusionar Todos')
                ->icon('heroicon-o-arrows-right-left')
                ->color('danger')
                ->visible(fn () => $this->duplicatesSearched)
                ->url(fn () => DuplicadosResource::getUrl('fusionar-todos')),
        ];
    }

    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();

        if (! $this->duplicatesSearched) {
            return $query?->whereRaw('0 = 1');
        }

        return CustomerDuplicateSearchService::applySearchScope($query);
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->emptyStateHeading(
                $this->duplicatesSearched
                    ? 'Sin duplicados'
                    : 'Buscar duplicados'
            )
            ->emptyStateDescription(
                $this->duplicatesSearched
                    ? 'No se encontraron clientes con el mismo DNI y nombre compatible, ni con el mismo nombre completo y teléfono compartido.'
                    : 'Pulsa «Buscar duplicados» para escanear clientes con el mismo DNI y nombre compatible, o con el mismo nombre completo y algún teléfono compartido.'
            );
    }

    protected function getHeaderWidgets(): array
    {
        return [
            DuplicadosStatsWidget::make([
                'duplicatesSearched' => $this->duplicatesSearched,
            ]),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            FusionadosWidget::class,
        ];
    }
}
