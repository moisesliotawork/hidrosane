<?php

namespace App\Filament\SuperAdmin\Resources\SuperAsignarResource\Pages;

use App\Filament\SuperAdmin\Resources\SuperAsignarResource;
use App\Models\Note;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

class ManageSuperAsignar extends Page implements HasForms
{
    use InteractsWithForms;

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

    /** @var list<int> */
    public array $phoneNoteIds = [];

    /** @var array<string, mixed> */
    public array $assignmentData = [];

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

    public function form(Form $form): Form
    {
        return $form
            ->schema(SuperAsignarResource::assignmentFormSchema())
            ->statePath('assignmentData');
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

        if (! $note) {
            $this->foundNoteId = null;
            $this->notFoundMessage = "No se encontró ninguna nota con el número {$normalized}.";
            $this->assignmentData = [];
            $this->form->fill([]);

            return;
        }

        $this->selectNoteForAssignment($note);
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

        if ($result['notes']->isEmpty()) {
            $this->foundNoteId = null;
            $this->assignmentData = [];
            $this->form->fill([]);

            return;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (strlen($digits) === 9) {
            $this->searchPhone = implode(' ', str_split($digits, 3));
        }
    }

    public function selectNoteForAssignment(Note|int $note): void
    {
        if (! $note instanceof Note) {
            $note = Note::query()
                ->with(SuperAsignarResource::noteEagerLoads())
                ->findOrFail($note);
        }

        $this->foundNoteId = $note->id;
        $this->notFoundMessage = null;
        $this->assignmentData = [
            'comercial_id' => $note->reten
                ? '__RETEN__'
                : ($note->comercial_id ?: null),
            'assignment_date' => $note->assignment_date?->format('Y-m-d'),
        ];
        $this->form->fill($this->assignmentData);
    }

    public function assignNote(): void
    {
        $note = $this->foundNote;

        if (! $note instanceof Note) {
            Notification::make()
                ->title('Selecciona una nota antes de asignar')
                ->warning()
                ->send();

            return;
        }

        $data = $this->form->getState();

        SuperAsignarResource::applyAssignment($note, $data);

        $note->refresh();
        $this->selectNoteForAssignment($note);
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
        $this->phoneNoteIds = [];
        $this->assignmentData = [];
        $this->form->fill([]);
    }

    protected function resetPhoneSearch(): void
    {
        $this->searchPhone = '';
        $this->searchedByPhone = false;
        $this->phoneSearchMessage = null;
        $this->matchedCustomersLabel = null;
        $this->phoneNoteIds = [];
    }

    protected function resetNoteSearch(): void
    {
        $this->searchNroNota = '';
        $this->searchedByNote = false;
        $this->notFoundMessage = null;
        $this->foundNoteId = null;
        $this->assignmentData = [];
        $this->form->fill([]);
    }

    protected function getForms(): array
    {
        return ['form'];
    }
}
