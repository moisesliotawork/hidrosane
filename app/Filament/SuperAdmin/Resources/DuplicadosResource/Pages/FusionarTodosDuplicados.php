<?php

namespace App\Filament\SuperAdmin\Resources\DuplicadosResource\Pages;

use App\Filament\SuperAdmin\Resources\DuplicadosResource;
use App\Services\CustomerDuplicateSearchService;
use App\Services\CustomerMergeService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class FusionarTodosDuplicados extends Page
{
    protected static string $resource = DuplicadosResource::class;

    protected static string $view = 'filament.super-admin.resources.duplicados.fusionar-todos';

    protected static ?string $title = 'Fusionar Todos';

    protected static bool $shouldRegisterNavigation = false;

    /** @var list<array<string, mixed>> */
    public array $pairs = [];

    /** @var list<int> */
    public array $selectedCustomerIds = [];

    public function mount(): void
    {
        $this->loadPairs();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('volver')
                ->label('Volver a Duplicados')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(DuplicadosResource::getUrl('index')),

            Action::make('fusionarSeleccionados')
                ->label('Fusionar seleccionados')
                ->icon('heroicon-o-arrows-right-left')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Confirmar fusión masiva')
                ->modalDescription('Se fusionarán los pares completos marcados. El cliente más antiguo se conserva; el otro pasa a «Registros ya fusionados». Las notas y ventas se reasignan igual que en una fusión individual.')
                ->modalSubmitActionLabel('Sí, fusionar')
                ->action(fn () => $this->fusionarSeleccionados()),
        ];
    }

    public function loadPairs(): void
    {
        $this->pairs = CustomerDuplicateSearchService::findAutoMergePairsOfTwo();

        $this->selectedCustomerIds = collect($this->pairs)
            ->flatMap(fn (array $pair) => collect($pair['customers'])->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function fusionarSeleccionados(): void
    {
        $pairsToMerge = [];

        foreach ($this->pairs as $pair) {
            $customerIds = collect($pair['customers'])->pluck('id')->map(fn ($id) => (int) $id)->all();
            $selectedInPair = array_intersect(
                $customerIds,
                array_map('intval', $this->selectedCustomerIds),
            );

            if (count($selectedInPair) !== 2) {
                continue;
            }

            $pairsToMerge[] = [
                'keeper_id' => (int) $pair['keeper_id'],
                'to_delete_id' => (int) $pair['to_delete_id'],
            ];
        }

        if ($pairsToMerge === []) {
            Notification::make()
                ->title('Sin pares para fusionar')
                ->body('Marca los dos registros de al menos un par (mismo nombre y teléfono compartido).')
                ->warning()
                ->send();

            return;
        }

        $result = app(CustomerMergeService::class)->mergePairs($pairsToMerge, auth()->id());

        CustomerDuplicateSearchService::refreshDuplicateIdsInSession();
        $this->loadPairs();

        if ($result['merged'] > 0) {
            Notification::make()
                ->title('Fusión masiva completada')
                ->body("Se fusionaron {$result['merged']} par(es).")
                ->success()
                ->send();
        }

        if ($result['failed'] !== []) {
            Notification::make()
                ->title('Algunas fusiones fallaron')
                ->body(implode("\n", $result['failed']))
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function selectAll(): void
    {
        $this->selectedCustomerIds = collect($this->pairs)
            ->flatMap(fn (array $pair) => collect($pair['customers'])->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function deselectAll(): void
    {
        $this->selectedCustomerIds = [];
    }
}
