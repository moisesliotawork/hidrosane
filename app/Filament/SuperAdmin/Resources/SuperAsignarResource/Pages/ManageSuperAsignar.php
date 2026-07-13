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

    public ?int $foundNoteId = null;

    public ?string $notFoundMessage = null;

    public ?string $listSearchMessage = null;

    public ?string $matchedCustomersLabel = null;

    public ?string $matchedCustomersPhones = null;

    public ?int $expandedNoteId = null;

    /** @var list<int> */
    public array $resultNoteIds = [];

    /** @var array<string, mixed> */
    public array $assignmentData = [
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

    public function getFoundNoteProperty(): ?Note
    {
        if ($this->foundNoteId === null) {
            return null;
        }

        return Note::query()
            ->with(SuperAsignarResource::noteEagerLoads())
            ->find($this->foundNoteId);
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

        $this->resetListSearch();

        $normalized = SuperAsignarResource::normalizeNroNota($value);

        /** @var Note|null $note */
        $note = Note::query()
            ->with(SuperAsignarResource::noteEagerLoads())
            ->where('nro_nota', $normalized)
            ->first();

        $this->searchedByNote = true;
        $this->searchNroNota = $normalized;
        $this->expandedNoteId = null;
        $this->assignmentData = [
            'comercial_id' => '',
            'assignment_date' => null,
        ];

        if (! $note) {
            $this->foundNoteId = null;
            $this->notFoundMessage = "No se encontró ninguna nota con el número {$normalized}.";

            return;
        }

        $this->foundNoteId = $note->id;
        $this->notFoundMessage = null;
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

        if ($result['notes']->isEmpty()) {
            $this->foundNoteId = null;
            $this->assignmentData = [
                'comercial_id' => '',
                'assignment_date' => null,
            ];
        }
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

        $this->foundNoteId = $note->id;
        $this->assignmentData = [
            'comercial_id' => $note->reten
                ? '__RETEN__'
                : (string) ($note->comercial_id ?? ''),
            'assignment_date' => $note->assignment_date?->format('Y-m-d'),
        ];
    }

    public function assignNote(): void
    {
        $noteId = $this->expandedNoteId ?? $this->foundNoteId;

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
        $this->foundNoteId = $note->id;
        $this->loadAssignmentDataForNote($note);
    }

    public function clearSearch(): void
    {
        $this->searchNroNota = '';
        $this->searchPhone = '';
        $this->searchCustomerName = '';
        $this->searchedByNote = false;
        $this->searchedByPhone = false;
        $this->searchedByCustomerName = false;
        $this->foundNoteId = null;
        $this->notFoundMessage = null;
        $this->listSearchMessage = null;
        $this->matchedCustomersLabel = null;
        $this->matchedCustomersPhones = null;
        $this->resultNoteIds = [];
        $this->expandedNoteId = null;
        $this->assignmentData = [
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
        $this->foundNoteId = null;
        $this->expandedNoteId = null;
        $this->assignmentData = [
            'comercial_id' => '',
            'assignment_date' => null,
        ];
    }
}
