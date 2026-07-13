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

    public bool $searchedByNote = false;

    public bool $searchedByPhone = false;

    public ?int $foundNoteId = null;

    public ?string $notFoundMessage = null;

    public ?string $phoneSearchMessage = null;

    public ?string $matchedCustomersLabel = null;

    public ?string $matchedCustomersPhones = null;

    public ?int $expandedNoteId = null;

    /** @var list<int> */
    public array $phoneNoteIds = [];

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

    public function getPhoneNotesProperty(): Collection
    {
        if ($this->phoneNoteIds === []) {
            return collect();
        }

        return Note::query()
            ->with(SuperAsignarResource::noteEagerLoads())
            ->whereIn('id', $this->phoneNoteIds)
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

        $this->resetPhoneSearch();

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

        $result = SuperAsignarResource::findNotesByPhone($phone);

        $this->searchedByPhone = true;
        $this->phoneSearchMessage = $result['message'];
        $this->phoneNoteIds = $result['notes']->pluck('id')->all();
        $this->matchedCustomersLabel = $result['customers']
            ->map(fn ($customer): string => strtoupper(trim("{$customer->first_names} {$customer->last_names}")))
            ->unique()
            ->values()
            ->implode(' · ');
        $this->matchedCustomersPhones = $result['customers']
            ->flatMap(function ($customer): array {
                $phones = array_filter([
                    $customer->phone1_commercial,
                    $customer->phone,
                    $customer->phone2_commercial ?? null,
                ]);

                return array_map(
                    fn (?string $phone): string => SuperAsignarResource::formatPhoneDisplay($phone),
                    $phones,
                );
            })
            ->unique()
            ->values()
            ->implode(' · ');

        if ($result['notes']->isEmpty()) {
            $this->foundNoteId = null;
            $this->expandedNoteId = null;
            $this->assignmentData = [
                'comercial_id' => '',
                'assignment_date' => null,
            ];

            return;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (strlen($digits) === 9) {
            $this->searchPhone = SuperAsignarResource::formatPhoneDisplay($digits);
        }

        $this->expandedNoteId = null;
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
        $this->searchedByNote = false;
        $this->searchedByPhone = false;
        $this->foundNoteId = null;
        $this->notFoundMessage = null;
        $this->phoneSearchMessage = null;
        $this->matchedCustomersLabel = null;
        $this->matchedCustomersPhones = null;
        $this->phoneNoteIds = [];
        $this->expandedNoteId = null;
        $this->assignmentData = [
            'comercial_id' => '',
            'assignment_date' => null,
        ];
    }

    protected function resetPhoneSearch(): void
    {
        $this->searchPhone = '';
        $this->searchedByPhone = false;
        $this->phoneSearchMessage = null;
        $this->matchedCustomersLabel = null;
        $this->matchedCustomersPhones = null;
        $this->phoneNoteIds = [];
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
