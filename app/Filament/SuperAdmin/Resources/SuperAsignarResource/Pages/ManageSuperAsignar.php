<?php

namespace App\Filament\SuperAdmin\Resources\SuperAsignarResource\Pages;

use App\Filament\SuperAdmin\Resources\SuperAsignarResource;
use App\Models\Note;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

class ManageSuperAsignar extends Page
{
    protected static string $resource = SuperAsignarResource::class;

    protected static string $view = 'filament.superAdmin.resources.super-asignar-resource.pages.manage-super-asignar';

    protected static ?string $title = 'Super_Asignar';

    public string $searchNroNota = '';

    public string $searchPhone = '';

    public string $searchCustomerName = '';

    public bool $searchedByNote = false;

    public bool $searchedByPhone = false;

    public bool $searchedByCustomerName = false;

    public ?string $notFoundMessage = null;

    public ?string $noteSearchFeedback = null;

    public ?string $listSearchMessage = null;

    public ?string $matchedCustomersLabel = null;

    public ?string $matchedCustomersPhones = null;

    public ?int $expandedNoteId = null;

    /** @var list<int> */
    public array $resultNoteIds = [];

    /** @var list<int> */
    public array $selectedNoteIds = [];

    /** @var array<string, mixed> */
    public array $assignmentData = [
        'comercial_id' => '',
        'assignment_date' => null,
    ];

    /** @var array<string, mixed> */
    public array $bulkAssignmentData = [
        'comercial_id' => '',
        'assignment_date' => null,
    ];

    public function getAssignableOptionsProperty(): array
    {
        return [
            '__RETEN__' => 'COMERCIAL RETÉN',
            '' => 'Sin asignar',
        ] + SuperAsignarResource::assignableUserOptions(labeled: true);
    }

    public function getResultNotesProperty(): Collection
    {
        if ($this->resultNoteIds === []) {
            return collect();
        }

        return Note::query()
            ->with(SuperAsignarResource::noteEagerLoads())
            ->whereIn('id', $this->resultNoteIds)
            ->orderByDesc('created_at')
            ->get();
    }

    public function getSelectedNotesProperty(): Collection
    {
        if ($this->selectedNoteIds === []) {
            return collect();
        }

        return Note::query()
            ->with(SuperAsignarResource::noteEagerLoads())
            ->whereIn('id', $this->selectedNoteIds)
            ->get()
            ->sortBy(fn (Note $note): int => array_search($note->id, $this->selectedNoteIds, true) ?: 0)
            ->values();
    }

    public function searchNote(): void
    {
        $value = trim($this->searchNroNota);

        if ($value === '') {
            Notification::make()
                ->title('Introduce un número de nota')
                ->warning()
                ->send();

            return;
        }

        $numbers = SuperAsignarResource::parseNroNotaInputs($value);

        if ($numbers === []) {
            Notification::make()
                ->title('Número de nota no válido')
                ->warning()
                ->send();

            return;
        }

        if (count($numbers) > SuperAsignarResource::MAX_SELECTED_NOTES) {
            Notification::make()
                ->title('Máximo ' . SuperAsignarResource::MAX_SELECTED_NOTES . ' notas por búsqueda')
                ->warning()
                ->send();

            $numbers = array_slice($numbers, 0, SuperAsignarResource::MAX_SELECTED_NOTES);
        }

        $this->resetListSearch();

        $added = 0;
        $duplicates = 0;
        $notFound = [];
        $limitReached = false;

        foreach ($numbers as $normalized) {
            if (count($this->selectedNoteIds) >= SuperAsignarResource::MAX_SELECTED_NOTES) {
                $limitReached = true;
                break;
            }

            $note = Note::query()
                ->where('nro_nota', $normalized)
                ->first();

            if (! $note) {
                $notFound[] = SuperAsignarResource::formatNroNota($normalized);
                continue;
            }

            if (in_array($note->id, $this->selectedNoteIds, true)) {
                $duplicates++;
                continue;
            }

            $this->selectedNoteIds[] = $note->id;
            $added++;
        }

        $this->searchedByNote = true;
        $this->searchNroNota = '';
        $this->notFoundMessage = null;
        $this->noteSearchFeedback = $this->buildSelectionFeedback($added, $duplicates, $notFound, $limitReached);
    }

    public function searchNotesByPhone(): void
    {
        $phone = trim($this->searchPhone);

        if ($phone === '') {
            Notification::make()
                ->title('Introduce un número de teléfono')
                ->warning()
                ->send();

            return;
        }

        $this->resetNoteSearch();
        $this->resetListSearch();

        $result = SuperAsignarResource::findNotesByPhone($phone);

        $this->searchedByPhone = true;
        $this->applyListSearchResult($result);

        $digits = preg_replace('/\D+/', '', $phone);

        if (strlen($digits) === 9) {
            $this->searchPhone = SuperAsignarResource::formatPhoneDisplay($digits);
        }
    }

    public function searchNotesByCustomerName(): void
    {
        $name = trim($this->searchCustomerName);

        if ($name === '') {
            Notification::make()
                ->title('Introduce el nombre del cliente')
                ->warning()
                ->send();

            return;
        }

        $this->resetNoteSearch();
        $this->resetListSearch();

        $result = SuperAsignarResource::findNotesByCustomerName($name);

        $this->searchedByCustomerName = true;
        $this->searchCustomerName = trim(preg_replace('/\s+/u', ' ', $name));
        $this->applyListSearchResult($result);
    }

    /**
     * @param  array{
     *     notes: Collection<int, Note>,
     *     customers: Collection<int, \App\Models\Customer>,
     *     message: ?string
     * }  $result
     */
    protected function applyListSearchResult(array $result): void
    {
        $this->listSearchMessage = $result['message'];
        $this->resultNoteIds = $result['notes']->pluck('id')->all();
        $this->matchedCustomersLabel = SuperAsignarResource::customersLabel($result['customers']);
        $this->matchedCustomersPhones = SuperAsignarResource::customersPhonesLabel($result['customers']);
        $this->expandedNoteId = null;
    }

    public function toggleSelection(int $noteId): void
    {
        if (in_array($noteId, $this->selectedNoteIds, true)) {
            $this->selectedNoteIds = array_values(array_filter(
                $this->selectedNoteIds,
                fn (int $id): bool => $id !== $noteId,
            ));

            return;
        }

        if (count($this->selectedNoteIds) >= SuperAsignarResource::MAX_SELECTED_NOTES) {
            Notification::make()
                ->title('Máximo ' . SuperAsignarResource::MAX_SELECTED_NOTES . ' notas seleccionadas')
                ->warning()
                ->send();

            return;
        }

        $this->selectedNoteIds[] = $noteId;
    }

    public function selectAllResultNotes(): void
    {
        $added = 0;

        foreach ($this->resultNoteIds as $noteId) {
            if (count($this->selectedNoteIds) >= SuperAsignarResource::MAX_SELECTED_NOTES) {
                break;
            }

            if (in_array($noteId, $this->selectedNoteIds, true)) {
                continue;
            }

            $this->selectedNoteIds[] = $noteId;
            $added++;
        }

        if ($added === 0 && count($this->selectedNoteIds) >= SuperAsignarResource::MAX_SELECTED_NOTES) {
            Notification::make()
                ->title('Ya tienes el máximo de ' . SuperAsignarResource::MAX_SELECTED_NOTES . ' notas seleccionadas')
                ->warning()
                ->send();
        }
    }

    public function removeFromSelection(int $noteId): void
    {
        $this->selectedNoteIds = array_values(array_filter(
            $this->selectedNoteIds,
            fn (int $id): bool => $id !== $noteId,
        ));
    }

    public function clearSelection(): void
    {
        $this->selectedNoteIds = [];
        $this->bulkAssignmentData = [
            'comercial_id' => '',
            'assignment_date' => null,
        ];
    }

    public function openReassignForm(int $noteId): void
    {
        if ($this->expandedNoteId === $noteId) {
            $this->expandedNoteId = null;

            return;
        }

        $this->expandedNoteId = $noteId;
        $this->loadAssignmentDataForNote($noteId);
    }

    public function loadAssignmentDataForNote(Note|int $note): void
    {
        if (! $note instanceof Note) {
            $note = Note::query()
                ->with(SuperAsignarResource::noteEagerLoads())
                ->findOrFail($note);
        }

        $this->assignmentData = [
            'comercial_id' => $note->reten
                ? '__RETEN__'
                : (string) ($note->comercial_id ?? ''),
            'assignment_date' => $note->assignment_date?->format('Y-m-d'),
        ];
    }

    public function assignNote(): void
    {
        $noteId = $this->expandedNoteId;

        if ($noteId === null) {
            Notification::make()
                ->title('Selecciona una nota antes de asignar')
                ->warning()
                ->send();

            return;
        }

        $note = Note::query()
            ->with(SuperAsignarResource::noteEagerLoads())
            ->find($noteId);

        if (! $note instanceof Note) {
            Notification::make()
                ->title('Nota no encontrada')
                ->danger()
                ->send();

            return;
        }

        $data = $this->assignmentData;

        if (($data['comercial_id'] ?? '') === '') {
            $data['comercial_id'] = null;
        }

        SuperAsignarResource::applyAssignment($note, $data);

        $note->refresh();
        $this->loadAssignmentDataForNote($note);
    }

    public function assignSelectedNotes(): void
    {
        if ($this->selectedNoteIds === []) {
            Notification::make()
                ->title('Selecciona al menos una nota')
                ->warning()
                ->send();

            return;
        }

        $data = $this->bulkAssignmentData;

        if (($data['comercial_id'] ?? '') === '') {
            $data['comercial_id'] = null;
        }

        $notes = Note::query()
            ->with(SuperAsignarResource::noteEagerLoads())
            ->whereIn('id', $this->selectedNoteIds)
            ->get();

        if ($notes->isEmpty()) {
            Notification::make()
                ->title('No se encontraron las notas seleccionadas')
                ->danger()
                ->send();

            $this->selectedNoteIds = [];

            return;
        }

        SuperAsignarResource::applyBulkAssignment($notes, $data);

        $this->selectedNoteIds = [];
        $this->bulkAssignmentData = [
            'comercial_id' => '',
            'assignment_date' => null,
        ];
        $this->expandedNoteId = null;
    }

    public function clearSearch(): void
    {
        $this->searchNroNota = '';
        $this->searchPhone = '';
        $this->searchCustomerName = '';
        $this->searchedByNote = false;
        $this->searchedByPhone = false;
        $this->searchedByCustomerName = false;
        $this->notFoundMessage = null;
        $this->noteSearchFeedback = null;
        $this->listSearchMessage = null;
        $this->matchedCustomersLabel = null;
        $this->matchedCustomersPhones = null;
        $this->resultNoteIds = [];
        $this->selectedNoteIds = [];
        $this->expandedNoteId = null;
        $this->assignmentData = [
            'comercial_id' => '',
            'assignment_date' => null,
        ];
        $this->bulkAssignmentData = [
            'comercial_id' => '',
            'assignment_date' => null,
        ];
    }

    protected function resetListSearch(): void
    {
        $this->searchPhone = '';
        $this->searchCustomerName = '';
        $this->searchedByPhone = false;
        $this->searchedByCustomerName = false;
        $this->listSearchMessage = null;
        $this->matchedCustomersLabel = null;
        $this->matchedCustomersPhones = null;
        $this->resultNoteIds = [];
        $this->expandedNoteId = null;
    }

    protected function resetNoteSearch(): void
    {
        $this->searchNroNota = '';
        $this->searchedByNote = false;
        $this->notFoundMessage = null;
        $this->noteSearchFeedback = null;
        $this->expandedNoteId = null;
        $this->assignmentData = [
            'comercial_id' => '',
            'assignment_date' => null,
        ];
    }

    /**
     * @param  list<string>  $notFound
     */
    protected function buildSelectionFeedback(int $added, int $duplicates, array $notFound, bool $limitReached): ?string
    {
        $parts = [];

        if ($added > 0) {
            $parts[] = $added === 1
                ? '1 nota agregada a la selección.'
                : "{$added} notas agregadas a la selección.";
        }

        if ($duplicates > 0) {
            $parts[] = $duplicates === 1
                ? '1 nota ya estaba seleccionada.'
                : "{$duplicates} notas ya estaban seleccionadas.";
        }

        if ($notFound !== []) {
            $parts[] = 'No encontradas: ' . implode(', ', $notFound) . '.';
        }

        if ($limitReached) {
            $parts[] = 'Se alcanzó el máximo de ' . SuperAsignarResource::MAX_SELECTED_NOTES . ' notas.';
        }

        if ($parts === []) {
            return 'No se agregó ninguna nota a la selección.';
        }

        return implode(' ', $parts);
    }
}
